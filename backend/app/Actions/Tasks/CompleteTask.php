<?php

namespace App\Actions\Tasks;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Enums\TaskStatus;
use App\Models\Task;

class CompleteTask extends Action
{
    public function handle(Task $task): ActionResult
    {
        if ($task->status === TaskStatus::Completed) {
            return ActionResult::fail('Esta tarefa já foi concluída.');
        }

        $task->status = TaskStatus::Completed;
        $task->completed_at = now();
        $task->save();

        return ActionResult::ok($task->refresh()->load('customer'));
    }
}
