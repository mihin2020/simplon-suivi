<?php

namespace App\Actions\Campus;

use App\Enums\CohortLearnerStatus;
use App\Models\Cohort;
use App\Models\Learner;

class EnrollCohortLearners
{
    public function __construct(private readonly ApplyFormationPaymentPlanToLearner $applyPlan) {}

    /**
     * Enrolls existing learners; the payment plan is applied only to those who were not already enrolled.
     *
     * @param  array<int, string>  $learnerIds
     */
    public function execute(Cohort $cohort, array $learnerIds): void
    {
        $alreadyEnrolled = $cohort->learners()
            ->whereIn('learners.id', $learnerIds)
            ->pluck('learners.id')
            ->all();

        $cohort->learners()->syncWithoutDetaching(
            collect($learnerIds)->mapWithKeys(fn (string $id) => [
                $id => ['enrolled_at' => now(), 'status' => CohortLearnerStatus::Actif->value],
            ])->all()
        );

        Learner::whereIn('id', array_diff($learnerIds, $alreadyEnrolled))
            ->get()
            ->each(fn (Learner $learner) => $this->applyPlan->execute($cohort, $learner));
    }
}
