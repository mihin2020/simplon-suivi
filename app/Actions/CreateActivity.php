<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Phase;
use App\Models\User;
use App\Services\PlanningNotifier;
use App\Support\PlanningAssignees;
use Illuminate\Support\Facades\DB;

class CreateActivity
{
    public function __construct(
        private PlanningAssignees $assignees,
        private PlanningNotifier $notifier,
    ) {}

    public function execute(Phase $phase, array $data, User $actor): Activity
    {
        return DB::transaction(function () use ($phase, $data, $actor) {
            $assigneeIds = $this->assignees->resolve($data['assignee_ids'] ?? null, $actor);
            unset($data['assignee_ids']);

            $activity = $phase->activities()->create([
                ...$data,
                'created_by' => $actor->id,
                'position' => ((int) $phase->activities()->max('position')) + 1,
                'weight' => $data['weight'] ?? 1,
                'resources' => $data['resources'] ?? [],
            ]);

            $activity->assignees()->sync($assigneeIds);

            $this->notifier->notifyNewAssignees($assigneeIds, [], 'activité', $activity->title, $activity->id, $actor, $phase->project_id);

            return $activity->load('assignees');
        });
    }
}
