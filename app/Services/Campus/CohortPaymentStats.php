<?php

namespace App\Services\Campus;

use App\Enums\PaymentStatus;
use App\Models\Cohort;

class CohortPaymentStats
{
    /**
     * @return array{total_collected: int, total_expected: int, total_remaining: int, paid_count: int, overdue_count: int}
     */
    public function for(Cohort $cohort): array
    {
        $totalCollected = $cohort->total_collected;
        $totalExpected = $cohort->total_expected;

        return [
            'total_collected' => $totalCollected,
            'total_expected' => $totalExpected,
            'total_remaining' => max(0, $totalExpected - $totalCollected),
            'paid_count' => $this->fullyPaidLearners($cohort),
            // At least one installment past its due date and still unpaid.
            'overdue_count' => $cohort->payments()->overdue()->distinct('learner_id')->count('learner_id'),
        ];
    }

    /** Learners who paid the whole cost of the formation. */
    private function fullyPaidLearners(Cohort $cohort): int
    {
        $unitCost = $cohort->campusFormation->total_cost ?? 0;

        if ($unitCost <= 0) {
            return 0;
        }

        // A grouped SUM cannot be expressed without a raw expression; it takes no user input.
        return $cohort->payments()
            ->where('status', PaymentStatus::Paye->value)
            ->selectRaw('learner_id, SUM(amount) as total_paid')
            ->groupBy('learner_id')
            ->pluck('total_paid', 'learner_id')
            ->filter(fn ($paid) => $paid >= $unitCost)
            ->count();
    }
}
