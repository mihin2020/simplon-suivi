<?php

namespace App\Actions\Campus;

use App\Enums\CohortLearnerStatus;
use App\Enums\CohortStatus;
use App\Models\Cohort;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCohort
{
    /**
     * Closes the cohort and marks its active learners as graduated.
     *
     * @throws ValidationException when the cohort is already closed
     */
    public function execute(Cohort $cohort): void
    {
        if ($cohort->status === CohortStatus::Cloturee) {
            throw ValidationException::withMessages(['cohort' => 'Cette cohorte est déjà clôturée.']);
        }

        DB::transaction(function () use ($cohort) {
            DB::table('cohort_learner')
                ->where('cohort_id', $cohort->id)
                ->where('status', CohortLearnerStatus::Actif->value)
                ->update(['status' => CohortLearnerStatus::Diplome->value]);

            $cohort->update(['status' => CohortStatus::Cloturee]);
        });
    }
}
