<?php

namespace App\Services;

use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Models\Todo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class TodoService
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * @return LengthAwarePaginator<int, Todo>
     */
    public function listForUser(User $user, Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return QueryBuilder::for(
            $user->todos()->with('user'),
            $request
        )
            ->allowedFilters([
                AllowedFilter::exact('priority'),
                AllowedFilter::partial('title'),
                AllowedFilter::callback('is_completed', function (Builder $query, mixed $value): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($parsed !== null) {
                        $query->where('is_completed', $parsed);
                    }
                }),
                AllowedFilter::callback('due_before', function (Builder $query, mixed $value): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }
                    try {
                        $end = Carbon::parse($value)->endOfDay();
                        $query->whereNotNull('due_at')->where('due_at', '<=', $end);
                    } catch (\Throwable) {
                        // Invalid date: ignore filter (avoid leaking errors for crafted query strings).
                    }
                }),
                AllowedFilter::callback('due_after', function (Builder $query, mixed $value): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }
                    try {
                        $start = Carbon::parse($value)->startOfDay();
                        $query->whereNotNull('due_at')->where('due_at', '>=', $start);
                    } catch (\Throwable) {
                        //
                    }
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('title'),
                AllowedSort::field('due_at'),
                $this->prioritySort(),
            ])
            ->defaultSorts(
                $this->prioritySort()->defaultDirection(SortDirection::Ascending),
                AllowedSort::field('created_at')->defaultDirection(SortDirection::Descending),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{total: int, open: int, completed: int, overdue: int, due_within_week: int}
     */
    public function statsForUser(User $user): array
    {
        $now = now();
        $weekAhead = $now->copy()->addWeek();

        $row = $user->todos()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as open')
            ->selectRaw('SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed')
            ->selectRaw(
                'SUM(CASE WHEN is_completed = 0 AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue',
                [$now]
            )
            ->selectRaw(
                'SUM(CASE WHEN is_completed = 0 AND due_at IS NOT NULL AND due_at >= ? AND due_at <= ? THEN 1 ELSE 0 END) as due_within_week',
                [$now, $weekAhead]
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'open' => (int) ($row->open ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'overdue' => (int) ($row->overdue ?? 0),
            'due_within_week' => (int) ($row->due_within_week ?? 0),
        ];
    }

    public function create(User $user, StoreTodoRequest $request): Todo
    {
        /** @var array{title: string, description?: string|null, priority?: string|null, due_at?: mixed, is_completed?: bool} $validated */
        $validated = $request->validated();
        $isCompleted = (bool) ($validated['is_completed'] ?? false);

        $attributes = collect($validated)
            ->only(['title', 'description'])
            ->merge([
                'priority' => $validated['priority'] ?? 'medium',
                'user_id' => $user->id,
                'due_at' => $validated['due_at'] ?? null,
                'is_completed' => $isCompleted,
                'completed_at' => $isCompleted ? now() : null,
            ])
            ->when(
                $request->hasFile('file'),
                function ($collection) use ($request) {
                    $path = $this->storeUploadedFile($request->file('file'));

                    return $collection->put('file_path', $path);
                }
            )
            ->all();

        return Todo::query()->create($attributes);
    }

    public function update(Todo $todo, UpdateTodoRequest $request): Todo
    {
        /** @var array{title: string, description?: string|null, priority?: string|null, due_at?: mixed, is_completed?: bool} $validated */
        $validated = $request->validated();

        $attributes = collect($validated)
            ->only(['title', 'description'])
            ->when(
                $request->filled('priority'),
                fn ($c) => $c->put('priority', $validated['priority'])
            )
            ->when(
                $request->has('due_at'),
                fn ($c) => $c->put('due_at', $validated['due_at'] ?? null)
            )
            ->when(
                $request->has('is_completed'),
                function ($c) use ($validated) {
                    $done = (bool) $validated['is_completed'];

                    return $c->put('is_completed', $done)->put('completed_at', $done ? now() : null);
                }
            )
            ->when(
                $request->hasFile('file'),
                function ($collection) use ($request, $todo) {
                    $this->deleteStoredFileIfExists($todo->file_path);

                    return $collection->put('file_path', $this->storeUploadedFile($request->file('file')));
                }
            )
            ->all();

        $todo->update($attributes);

        return $todo->fresh(['user']) ?? $todo;
    }

    public function toggleCompletion(Todo $todo): Todo
    {
        $todo->is_completed = ! $todo->is_completed;
        $todo->completed_at = $todo->is_completed ? now() : null;
        $todo->save();

        return $todo->fresh(['user']) ?? $todo;
    }

    public function delete(Todo $todo): void
    {
        $this->deleteStoredFileIfExists($todo->file_path);
        $todo->delete();
    }

    protected function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE);

        return min(max($perPage, 1), self::MAX_PER_PAGE);
    }

    protected function prioritySort(): AllowedSort
    {
        return AllowedSort::callback('priority', function (Builder $query, bool $descending, string $property): void {
            $direction = $descending ? 'DESC' : 'ASC';
            $query->orderByRaw(
                "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END {$direction}"
            );
        });
    }

    protected function storeUploadedFile(UploadedFile $file): string
    {
        $safeName = Str::uuid()->toString().'.pdf';

        return $file->storeAs('todos', $safeName, 'public');
    }

    protected function deleteStoredFileIfExists(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
