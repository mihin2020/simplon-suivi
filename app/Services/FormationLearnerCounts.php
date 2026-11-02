<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\LearnerStatus;
use Illuminate\Support\Facades\DB;

/**
 * Learner counts per formation in one grouped query, instead of one correlated sub-query per count and
 * per formation (statistics and project pages list dozens of formations).
 *
 * Soft-deleted learners are excluded, like the Eloquent `learners` relations do.
 */
class FormationLearnerCounts
{
    /**
     * @param  array<int, string>  $formationIds
     * @return array<string, array{total: int, male: int, female: int, in_progress: int, withdrawn: int, completed: int, moved: int}>
     */
    public function for(array $formationIds): array
    {
        $counts = array_fill_keys($formationIds, [
            'total' => 0, 'male' => 0, 'female' => 0,
            'in_progress' => 0, 'withdrawn' => 0, 'completed' => 0, 'moved' => 0,
        ]);

        if ($formationIds === []) {
            return $counts;
        }

        // A grouped COUNT cannot be expressed without a raw expression; it takes no user input.
        $rows = DB::table('formation_learner')
            ->join('learners', 'learners.id', '=', 'formation_learner.learner_id')
            ->whereNull('learners.deleted_at')
            ->whereIn('formation_learner.formation_id', $formationIds)
            ->groupBy('formation_learner.formation_id', 'formation_learner.status', 'learners.gender')
            ->selectRaw('formation_learner.formation_id as formation_id, formation_learner.status as status, learners.gender as gender, count(*) as total')
            ->get();

        foreach ($rows as $row) {
            $counts[$row->formation_id]['total'] += $row->total;

            match ($row->gender) {
                Gender::Male->value => $counts[$row->formation_id]['male'] += $row->total,
                Gender::Female->value => $counts[$row->formation_id]['female'] += $row->total,
                default => null,
            };

            match ($row->status) {
                LearnerStatus::InProgress->value => $counts[$row->formation_id]['in_progress'] += $row->total,
                LearnerStatus::Withdrawn->value => $counts[$row->formation_id]['withdrawn'] += $row->total,
                LearnerStatus::Completed->value => $counts[$row->formation_id]['completed'] += $row->total,
                LearnerStatus::Moved->value => $counts[$row->formation_id]['moved'] += $row->total,
                default => null,
            };
        }

        return $counts;
    }

    /**
     * Ids of the (non-deleted) learners of each formation, without hydrating a model per learner.
     *
     * @param  array<int, string>  $formationIds
     * @return array<string, array<int, string>>
     */
    public function learnerIds(array $formationIds): array
    {
        if ($formationIds === []) {
            return [];
        }

        return DB::table('formation_learner')
            ->join('learners', 'learners.id', '=', 'formation_learner.learner_id')
            ->whereNull('learners.deleted_at')
            ->whereIn('formation_learner.formation_id', $formationIds)
            ->get(['formation_learner.formation_id', 'formation_learner.learner_id'])
            ->groupBy('formation_id')
            ->map(fn ($rows) => $rows->pluck('learner_id')->all())
            ->all();
    }
}
