<?php

namespace App\Actions;

use App\Models\Phase;
use App\Models\Project;
use App\Models\User;
use App\Services\PlanningNotifier;
use App\Support\PlanningAssignees;
use Illuminate\Support\Facades\DB;

class CreatePhase
{
    public function __construct(
        private PlanningAssignees $assignees,
        private PlanningNotifier $notifier,
    ) {}

    public function execute(Project $project, array $data, User $actor): Phase
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $assigneeIds = $this->assignees->resolve($data['assignee_ids'] ?? null, $actor);
            unset($data['assignee_ids']);

            $phase = $project->phases()->create([
                ...$data,
                'created_by' => $actor->id,
                'position' => ((int) $project->phases()->max('position')) + 1,
                'weight' => $data['weight'] ?? 1,
            ]);

            $phase->assignees()->sync($assigneeIds);

            $this->notifier->notifyNewAssignees($assigneeIds, [], 'phase', $phase->name, $phase->id, $actor, $project->id);

            return $phase->load('assignees');
        });
    }
}
