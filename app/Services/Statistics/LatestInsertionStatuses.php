<?php

namespace App\Services\Statistics;

use Illuminate\Support\Facades\DB;

/** The most recent insertion status (searching, internship, employed, unemployed) of each learner. */
class LatestInsertionStatuses
{
    /**
     * @param  array<int, string>|null  $learnerIds  Restrict to these learners; null means everybody
     * @return array<string, string> [learner_id => status]
     */
    public function byLearner(?array $learnerIds = null): array
    {
        if ($learnerIds === []) {
            return [];
        }

        // MAX() of a column cannot be expressed without a raw expression; it takes no user input.
        $latest = DB::table('insertion_records')
            ->select('learner_id', DB::raw('MAX(status_changed_at) as max_date'))
            ->when($learnerIds !== null, fn ($query) => $query->whereIn('learner_id', $learnerIds))
            ->groupBy('learner_id');

        $rows = DB::table('insertion_records as ir')
            ->select('ir.learner_id', 'ir.status')
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('ir.learner_id', '=', 'latest.learner_id')
                    ->on('ir.status_changed_at', '=', 'latest.max_date');
            })
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->learner_id] = $row->status;
        }

        return $map;
    }
}
