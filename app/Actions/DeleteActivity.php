<?php

namespace App\Actions;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;

class DeleteActivity
{
    public function __construct(private DeleteTask $deleteTask) {}

    public function execute(Activity $activity): void
    {
        DB::transaction(function () use ($activity) {
            $activity->loadMissing('tasks');

            foreach ($activity->tasks as $task) {
                $this->deleteTask->execute($task);
            }

            $activity->assignees()->detach();
            $activity->delete();
        });
    }
}
