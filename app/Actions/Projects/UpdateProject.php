<?php

namespace App\Actions\Projects;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\ProjectStatus;
use App\Models\Learner;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class UpdateProject
{
    /**
     * Updates a project and its partners. When the project enters the "completed" or "archived" status, its
     * formations are finished and archived and its active learners graduate, all in the same transaction.
     *
     * @param  array<string, mixed>  $data  Validated fields, possibly with partner_ids
     * @return ProjectStatus|null The closing status the project has just entered, null for an ordinary update
     */
    public function execute(Project $project, array $data): ?ProjectStatus
    {
        $partnerIds = $data['partner_ids'] ?? [];
        unset($data['partner_ids']);

        $closing = $this->closingStatusEntered($project, $data['status'] ?? null);

        DB::transaction(function () use ($project, $data, $partnerIds, $closing) {
            if ($closing) {
                $data['ended_at'] = now();
                $this->closeFormations($project);
            }

            $project->update($data);
            $project->partners()->sync($partnerIds);
        });

        return $closing;
    }

    /** Only a real change of status counts: saving an already closed project again must not close it twice. */
    private function closingStatusEntered(Project $project, ?string $newStatus): ?ProjectStatus
    {
        $status = $newStatus === null ? null : ProjectStatus::tryFrom($newStatus);

        $isClosing = in_array($status, [ProjectStatus::Completed, ProjectStatus::Archived], true);

        return $isClosing && $project->status !== $status ? $status : null;
    }

    private function closeFormations(Project $project): void
    {
        // Finish what is still running, then archive everything that is finished.
        $project->formations()
            ->where('status', '!=', FormationStatus::Completed->value)
            ->update([
                'status' => FormationStatus::Completed->value,
                'ended_at' => now(),
            ]);

        $project->formations()
            ->where('status', FormationStatus::Completed->value)
            ->update(['status' => FormationStatus::Archived->value]);

        // Learners still in progress have finished their formation (deleted learners are left alone).
        DB::table('formation_learner')
            ->whereIn('formation_id', $project->formations()->select('id'))
            ->where('status', LearnerStatus::InProgress->value)
            ->whereIn('learner_id', Learner::query()->select('id'))
            ->update([
                'status' => LearnerStatus::Completed->value,
                'completed_at' => now(),
            ]);
    }
}
