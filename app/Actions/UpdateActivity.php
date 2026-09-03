<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\User;
use App\Services\PlanningNotifier;
use App\Support\PlanningAssignees;
use Illuminate\Support\Facades\DB;

class UpdateActivity
{
    public function __construct(
        private PlanningAssignees $assignees,
        private PlanningNotifier $notifier,
    ) {}

    public function execute(Activity $activity, array $data, User $actor): Activity
    {
        return DB::transaction(function () use ($activity, $data, $actor) {
            $previousIds = $activity->assignees()->pluck('users.id')->all();
            $assigneeIds = $this->assignees->resolve($data['assignee_ids'] ?? null, $actor);
            unset($data['assignee_ids']);

            $activity->update($data);
            $activity->assignees()->sync($assigneeIds);

            $activity->loadMissing('phase');

            $this->notifier->notifyNewAssignees($assigneeIds, $previousIds, 'activité', $activity->title, $activity->id, $actor, $activity->phase->project_id);

            return $activity->fresh(['assignees']);
        });
    }
}
