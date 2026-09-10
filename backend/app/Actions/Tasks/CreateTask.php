<?php

namespace App\Actions\Tasks;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\User;

class CreateTask extends Action
{
    public function handle(Customer $customer, array $data, ?User $creator = null): ActionResult
    {
        $task = $customer->tasks()->create([
            'created_by' => $creator?->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'],
            'priority' => $data['priority'] ?? TaskPriority::Medium->value,
            'status' => TaskStatus::Pending,
        ]);

        return ActionResult::ok($task->load('customer'));
    }
}
