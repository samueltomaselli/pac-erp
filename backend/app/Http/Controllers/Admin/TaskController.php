<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\ReopenTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\IndexTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Customer;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $tasks = Task::query()
            ->with('customer')
            ->status($request->input('status'))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->input('priority')))
            ->when($request->filled('due_from'), fn ($query) => $query->whereDate('due_date', '>=', $request->date('due_from')))
            ->when($request->filled('due_until'), fn ($query) => $query->whereDate('due_date', '<=', $request->date('due_until')))
            ->when($request->boolean('overdue'), fn ($query) => $query->overdue())
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, CreateTask $createTask): JsonResponse
    {
        $customer = Customer::findOrFail($request->integer('customer_id'));

        $result = $createTask->handle($customer, $request->validated(), $request->user());

        return (new TaskResource($result->data))->response()->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load('customer'));
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $updateTask): TaskResource
    {
        $result = $updateTask->handle($task, $request->validated());

        return new TaskResource($result->data);
    }

    public function complete(Task $task, CompleteTask $completeTask): JsonResponse
    {
        $result = $completeTask->handle($task);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new TaskResource($result->data))->response();
    }

    public function reopen(Task $task, ReopenTask $reopenTask): JsonResponse
    {
        $result = $reopenTask->handle($task);

        if (! $result->successful()) {
            return response()->json(['message' => $result->message], 422);
        }

        return (new TaskResource($result->data))->response();
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Tarefa removida.']);
    }
}
