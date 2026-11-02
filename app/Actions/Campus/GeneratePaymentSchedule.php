<?php

namespace App\Actions\Campus;

use App\Enums\PaymentStatus;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeneratePaymentSchedule
{
    /**
     * Replaces the pending and overdue installments of one learner with a new schedule. Paid and refunded
     * installments are kept and the new ones are numbered after them.
     *
     * @param  list<array{amount: int, due_date?: string|null}>  $installments
     */
    public function forLearner(Cohort $cohort, string $learnerId, array $installments): void
    {
        DB::transaction(fn () => $this->replace($cohort, $learnerId, $installments));
    }

    /**
     * Applies the same schedule to every active learner of the cohort. An installment is either a fixed
     * amount or a percentage of the formation cost.
     *
     * @param  list<array{type: string, value: int|float|string, due_date?: string|null}>  $installments
     * @return int Number of learners scheduled
     *
     * @throws ValidationException when nobody is active in the cohort
     */
    public function forActiveLearners(Cohort $cohort, array $installments): int
    {
        $cohort->load(['campusFormation' => fn ($query) => $query->withTrashed()]);
        $totalCost = $cohort->campusFormation->total_cost ?? 0;

        $learners = $cohort->activeLearners()->get(['learners.id']);

        if ($learners->isEmpty()) {
            throw ValidationException::withMessages(['cohort' => 'Aucun apprenant actif dans cette cohorte.']);
        }

        $amounts = array_map(fn (array $installment) => [
            'amount' => max(1, $installment['type'] === 'percentage'
                ? (int) round($installment['value'] / 100 * $totalCost)
                : (int) $installment['value']),
            'due_date' => $installment['due_date'] ?? null,
        ], $installments);

        DB::transaction(function () use ($cohort, $learners, $amounts) {
            foreach ($learners as $learner) {
                $this->replace($cohort, $learner->id, $amounts);
            }
        });

        return $learners->count();
    }

    /**
     * @param  list<array{amount: int, due_date?: string|null}>  $installments
     */
    private function replace(Cohort $cohort, string $learnerId, array $installments): void
    {
        $payments = fn () => Payment::where('cohort_id', $cohort->id)->where('learner_id', $learnerId);

        $kept = $payments()
            ->whereIn('status', [PaymentStatus::Paye->value, PaymentStatus::Rembourse->value])
            ->count();

        $payments()
            ->whereIn('status', [PaymentStatus::EnAttente->value, PaymentStatus::EnRetard->value])
            ->delete();

        foreach ($installments as $index => $installment) {
            Payment::create([
                'cohort_id' => $cohort->id,
                'learner_id' => $learnerId,
                'amount' => $installment['amount'],
                'installment_number' => $kept + $index + 1,
                'due_date' => $installment['due_date'] ?? now()->addMonths($index + 1)->toDateString(),
                'status' => PaymentStatus::EnAttente,
            ]);
        }
    }
}
