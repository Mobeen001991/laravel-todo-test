<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Http\Resources\TodoStatsResource;
use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TodoController extends Controller
{
    public function __construct(
        private readonly TodoService $todoService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Todo::class);

        $todos = $this->todoService->listForUser($request->user(), $request);

        return TodoResource::collection($todos);
    }

    public function stats(Request $request): TodoStatsResource
    {
        $this->authorize('viewAny', Todo::class);

        return new TodoStatsResource(
            $this->todoService->statsForUser($request->user())
        );
    }

    public function store(StoreTodoRequest $request): JsonResponse
    {
        $this->authorize('create', Todo::class);

        $todo = $this->todoService->create($request->user(), $request);

        return (new TodoResource($todo->loadMissing('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Todo $todo): TodoResource
    {
        $this->authorize('view', $todo);

        return new TodoResource($todo->loadMissing('user'));
    }

    public function update(UpdateTodoRequest $request, Todo $todo): TodoResource
    {
        $this->authorize('update', $todo);

        $updated = $this->todoService->update($todo, $request);

        return new TodoResource($updated);
    }

    public function toggleCompletion(Todo $todo): TodoResource
    {
        $this->authorize('update', $todo);

        $updated = $this->todoService->toggleCompletion($todo);

        return new TodoResource($updated);
    }

    public function destroy(Todo $todo): Response
    {
        $this->authorize('delete', $todo);

        $this->todoService->delete($todo);

        return response()->noContent();
    }
}
