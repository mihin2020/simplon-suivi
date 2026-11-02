<?php

namespace App\Services\Campus;

use App\Enums\CohortLearnerStatus;
use App\Enums\PaymentStatus;
use App\Models\Cohort;
use Illuminate\Support\Collection;

/** What the payments screen of a cohort shows: each learner's situation, the formation plan and the totals. */
class CohortPaymentOverview
{
    /**
     * Loads the formation on the cohort (even if deleted) and returns the screen's figures.
     *
     * @return array{total_cost: int, learnerPayments: Collection<int, array<string, mixed>>, formationPlan: list<array<string, mixed>>, stats: array<string, int>}
     */
    public function for(Cohort $cohort): array
    {
        $cohort->load([
            'campusFormation' => fn ($query) => $query->withTrashed()->with('installments'),
        ]);
        $totalCost = $cohort->campusFormation->total_cost ?? 0;

        return [
            'total_cost' => $totalCost,
            'learnerPayments' => $this->learnerPayments($cohort, $totalCost),
            'formationPlan' => $this->formationPlan($cohort),
            'stats' => [
                'total_expected' => $cohort->total_expected,
                'total_collected' => $cohort->total_collected,
                'total_remaining' => $cohort->total_remaining,
                'overdue_count' => $cohort->payments()->overdue()->distinct('learner_id')->count(),
            ],
        ];
    }

    private function learnerPayments(Cohort $cohort, int $totalCost): Collection
    {
        $paymentsByLearner = $cohort->payments()
            ->orderBy('installment_number')
            ->get()
            ->groupBy('learner_id');

        $learners = $cohort->learners()
            ->withPivot(['enrolled_at', 'status'])
            ->wherePivotIn('status', [CohortLearnerStatus::Actif->value, CohortLearnerStatus::Retrait->value])
            ->orderBy('last_name')
            ->get(['learners.id', 'first_name', 'last_name']);

        return $learners->map(function ($learner) use ($paymentsByLearner, $totalCost) {
            $payments = $paymentsByLearner->get($learner->id, collect());
            $paidAmount = $payments->filter(fn ($payment) => $payment->status === PaymentStatus::Paye)->sum('amount');
            $pivotStatus = $learner->getRelation('pivot')->status;
            $isAbandoned = $pivotStatus === CohortLearnerStatus::Retrait->value;

            return [
                'learner' => $learner,
                'cohort_status' => $pivotStatus,
                'is_abandoned' => $isAbandoned,
                'payments' => $payments->values(),
                'paid_amount' => $paidAmount,
                // Abandoned learners: remaining fees are no longer due.
                'remaining_amount' => $isAbandoned ? 0 : max(0, $totalCost - $paidAmount),
                'progress' => $isAbandoned || $totalCost <= 0
                    ? 0
                    : min(100, (int) round(($paidAmount / $totalCost) * 100)),
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    private function formationPlan(Cohort $cohort): array
    {
        $installments = $cohort->campusFormation?->installments;

        return $installments === null ? [] : $installments
            ->sortBy('position')
            ->values()
            ->map(fn ($installment) => [
                'position' => $installment->position,
                'type' => $installment->type,
                'value' => (int) $installment->value,
                'due_date' => optional($installment->due_date)->toDateString(),
            ])
            ->all();
    }
}
