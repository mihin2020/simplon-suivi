<?php

namespace App\Actions\Campus;

use App\Enums\PaymentStatus;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;

class ApplyFormationPaymentPlanToLearner
{
    /**
     * Create pending payment rows from the campus formation plan.
     * Skips if the learner already has payments on this cohort.
     */
    public function execute(Cohort $cohort, Learner $learner): int
    {
        $cohort->loadMissing(['campusFormation.installments']);

        $formation = $cohort->campusFormation;
        if (! $formation || $formation->installments->isEmpty()) {
            return 0;
        }

        $alreadyHasPayments = Payment::query()
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->whereNotIn('status', [PaymentStatus::Annule->value])
            ->exists();

        if ($alreadyHasPayments) {
            return 0;
        }

        $totalCost = (int) ($formation->total_cost ?? 0);
        $created = 0;

        foreach ($formation->installments->sortBy('position') as $installment) {
            Payment::create([
                'cohort_id' => $cohort->id,
                'learner_id' => $learner->id,
                'amount' => $installment->resolveAmount($totalCost),
                'installment_number' => $installment->position,
                'due_date' => $installment->due_date->toDateString(),
                'status' => PaymentStatus::EnAttente,
            ]);
            $created++;
        }

        return $created;
    }
}
