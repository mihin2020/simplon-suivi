<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Task;

class UpdateTaskStatus
{
    public function execute(Task $task, TaskStatus $status): Task
    {
        $task->update([
            'status' => $status,
            'completed_at' => $status->isCompleted() ? ($task->completed_at ?? now()) : null,
        ]);

        return $task->fresh(['assignees']);
    }
}
