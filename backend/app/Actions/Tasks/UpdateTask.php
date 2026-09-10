<?php

namespace App\Actions\Tasks;

use App\Actions\Action;
use App\Actions\Support\ActionResult;
use App\Models\Task;

class UpdateTask extends Action
{
    public function handle(Task $task, array $data): ActionResult
    {
        $task->fill($data)->save();

        return ActionResult::ok($task->refresh()->load('customer'));
    }
}
