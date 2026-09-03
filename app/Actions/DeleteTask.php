<?php

namespace App\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class DeleteTask
{
    public function execute(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->assignees()->detach();
            $task->delete();
        });
    }
}
