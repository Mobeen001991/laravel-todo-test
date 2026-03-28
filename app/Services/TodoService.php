<?php

namespace App\Services;

use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class TodoService
{
    /**
     * @return Collection<int, Todo>
     */
    public function listForUser(User $user, Request $request): Collection
    {
        return QueryBuilder::for(
            $user->todos()->with('user'),
            $request
        )
            ->allowedFilters([
                AllowedFilter::exact('priority'),
                AllowedFilter::partial('title'),
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('title'),
                $this->prioritySort(),
            ])
            ->defaultSorts(
                $this->prioritySort()->defaultDirection(SortDirection::Ascending),
                AllowedSort::field('created_at')->defaultDirection(SortDirection::Descending),
            )
            ->get();
    }

    public function create(User $user, StoreTodoRequest $request): Todo
    {
        /** @var array{title: string, description?: string|null, priority?: string|null} $validated */
        $validated = $request->validated();

        $attributes = collect($validated)
            ->only(['title', 'description'])
            ->merge([
                'priority' => $validated['priority'] ?? 'medium',
                'user_id' => $user->id,
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
        /** @var array{title: string, description?: string|null, priority?: string|null} $validated */
        $validated = $request->validated();

        $attributes = collect($validated)
            ->only(['title', 'description'])
            ->when(
                $request->filled('priority'),
                fn ($c) => $c->put('priority', $validated['priority'])
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

    public function delete(Todo $todo): void
    {
        $this->deleteStoredFileIfExists($todo->file_path);
        $todo->delete();
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
        $fileName = time().'_'.$file->getClientOriginalName();

        return $file->storeAs('todos', $fileName, 'public');
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
