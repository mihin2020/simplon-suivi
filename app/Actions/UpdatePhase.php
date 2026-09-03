<?php

namespace App\Actions;

use App\Models\Phase;
use App\Models\User;
use App\Services\PlanningNotifier;
use App\Support\PlanningAssignees;
use Illuminate\Support\Facades\DB;

class UpdatePhase
{
    public function __construct(
        private PlanningAssignees $assignees,
        private PlanningNotifier $notifier,
    ) {}

    public function execute(Phase $phase, array $data, User $actor): Phase
    {
        return DB::transaction(function () use ($phase, $data, $actor) {
            $previousIds = $phase->assignees()->pluck('users.id')->all();
            $assigneeIds = $this->assignees->resolve($data['assignee_ids'] ?? null, $actor);
            unset($data['assignee_ids']);

            $phase->update($data);
            $phase->assignees()->sync($assigneeIds);

            $this->notifier->notifyNewAssignees($assigneeIds, $previousIds, 'phase', $phase->name, $phase->id, $actor, $phase->project_id);

            return $phase->fresh(['assignees']);
        });
    }
}
