<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanningNotifier;
use App\Support\PlanningAssignees;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    public function __construct(
        private PlanningAssignees $assignees,
        private PlanningNotifier $notifier,
    ) {}

    public function execute(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $previousIds = $task->assignees()->pluck('users.id')->all();
            $assigneeIds = $this->assignees->resolve($data['assignee_ids'] ?? null, $actor);
            unset($data['assignee_ids']);

            if (isset($data['status'])) {
                $status = $data['status'] instanceof TaskStatus
                    ? $data['status']
                    : TaskStatus::from($data['status']);
                $data['completed_at'] = $status->isCompleted() ? ($task->completed_at ?? now()) : null;
            }

            $task->update($data);
            $task->assignees()->sync($assigneeIds);

            $task->loadMissing('activity.phase');

            $this->notifier->notifyNewAssignees($assigneeIds, $previousIds, 'tâche', $task->title, $task->id, $actor, $task->activity->phase->project_id);

            return $task->fresh(['assignees']);
        });
    }
}
