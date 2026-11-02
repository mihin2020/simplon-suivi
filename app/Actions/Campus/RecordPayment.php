<?php

namespace App\Actions\Campus;

use App\Enums\PaymentStatus;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    /**
     * Records a payment already received. It can never exceed what the learner still owes.
     *
     * @param  array{learner_id: string, amount: int, payment_method: string, paid_at?: string|null, notes?: string|null}  $data
     *
     * @throws ValidationException when the learner has settled everything or the amount is too high
     */
    public function execute(Cohort $cohort, array $data): Payment
    {
        $cohort->load(['campusFormation' => fn ($query) => $query->withTrashed()]);
        $totalCost = $cohort->campusFormation->total_cost ?? 0;

        $alreadyPaid = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->where('status', PaymentStatus::Paye->value)
            ->sum('amount');

        $remaining = $totalCost - $alreadyPaid;

        if ($remaining <= 0) {
            throw ValidationException::withMessages(['amount' => "Cet apprenant a déjà réglé l'intégralité des frais."]);
        }

        if ($data['amount'] > $remaining) {
            throw ValidationException::withMessages(['amount' => "Le montant dépasse le solde restant ({$remaining} FCFA)."]);
        }

        // Cancelled installments do not take a number.
        $number = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->whereNotIn('status', [PaymentStatus::Annule->value])
            ->count() + 1;

        $paidAt = $data['paid_at'] ?? now()->toDateString();

        return Payment::create([
            'cohort_id' => $cohort->id,
            'learner_id' => $data['learner_id'],
            'amount' => $data['amount'],
            'installment_number' => $number,
            'due_date' => $paidAt,
            'paid_at' => $paidAt,
            'status' => PaymentStatus::Paye,
            'payment_method' => $data['payment_method'],
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
