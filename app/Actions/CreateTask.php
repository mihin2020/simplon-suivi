<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanningNotifier;
use App\Support\PlanningAssignees;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    public function __construct(
        private PlanningAssignees $assignees,
        private PlanningNotifier $notifier,
    ) {}

    public function execute(Activity $activity, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($activity, $data, $actor) {
            $assigneeIds = $this->assignees->resolve($data['assignee_ids'] ?? null, $actor);
            unset($data['assignee_ids']);

            $activity->loadMissing('phase');

            $task = $activity->tasks()->create([
                ...$data,
                'created_by' => $actor->id,
                'position' => ((int) $activity->tasks()->max('position')) + 1,
                'status' => $data['status'] ?? TaskStatus::Todo,
                'completed_at' => null,
            ]);

            $task->assignees()->sync($assigneeIds);

            $this->notifier->notifyNewAssignees($assigneeIds, [], 'tâche', $task->title, $task->id, $actor, $activity->phase->project_id);

            return $task->load('assignees');
        });
    }
}
