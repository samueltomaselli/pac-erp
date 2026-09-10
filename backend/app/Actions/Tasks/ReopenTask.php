<?php

namespace App\Actions\Tasks;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\TaskStatus;
use App\Models\Task;

class ReopenTask extends Action
{
    public function handle(Task $task): ActionResult
    {
        if ($task->status === TaskStatus::Pending) {
            return ActionResult::fail('Esta tarefa já está pendente.');
        }

        $task->status = TaskStatus::Pending;
        $task->completed_at = null;
        $task->save();

        return ActionResult::ok($task->refresh()->load('customer'));
    }
}
