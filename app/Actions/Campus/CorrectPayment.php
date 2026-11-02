<?php

namespace App\Actions\Campus;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class CorrectPayment
{
    /**
     * Corrects the amount and dates of an installment. The learner's installments still counting toward
     * the plan (paid, pending, overdue), with this one replaced, must fit in the formation cost.
     *
     * @param  array{amount: int, due_date: string, paid_at?: string|null}  $data
     *
     * @throws ValidationException when the installment is locked or the amount exceeds the budget
     */
    public function execute(Payment $payment, array $data): void
    {
        if (in_array($payment->status, [PaymentStatus::Rembourse, PaymentStatus::Annule], true)) {
            throw ValidationException::withMessages(['payment' => 'Une tranche remboursée ou annulée ne peut pas être modifiée.']);
        }

        $payment->load('cohort');
        $payment->cohort->load(['campusFormation' => fn ($query) => $query->withTrashed()]);
        $totalCost = $payment->cohort->campusFormation->total_cost ?? 0;

        $otherAmount = Payment::where('cohort_id', $payment->cohort_id)
            ->where('learner_id', $payment->learner_id)
            ->whereIn('status', [
                PaymentStatus::Paye->value,
                PaymentStatus::EnAttente->value,
                PaymentStatus::EnRetard->value,
            ])
            ->where('id', '!=', $payment->id)
            ->sum('amount');

        if ($totalCost > 0 && $otherAmount + $data['amount'] > $totalCost) {
            $available = max(0, $totalCost - $otherAmount);

            throw ValidationException::withMessages(['amount' => "Le montant dépasse le solde planifiable ({$available} FCFA)."]);
        }

        $changes = ['amount' => $data['amount'], 'due_date' => $data['due_date']];

        // The collection date only exists for an installment that was collected.
        if ($payment->status === PaymentStatus::Paye) {
            $changes['paid_at'] = $data['paid_at'];
        }

        $payment->update($changes);
    }
}
