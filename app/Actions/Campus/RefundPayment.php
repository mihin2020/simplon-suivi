<?php

namespace App\Actions\Campus;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RefundPayment
{
    /**
     * @return Payment
     */
    public function execute(Payment $payment, int $refundAmount, string $motif, User $actor): Payment
    {
        if ($payment->status !== PaymentStatus::Paye) {
            throw ValidationException::withMessages([
                'payment' => 'Seule une tranche déjà encaissée peut être remboursée.',
            ]);
        }

        if ($refundAmount < 1 || $refundAmount > $payment->amount) {
            throw ValidationException::withMessages([
                'refund_amount' => "Le montant doit être compris entre 1 et {$payment->amount} FCFA.",
            ]);
        }

        $motif = trim($motif);
        if ($motif === '') {
            throw ValidationException::withMessages([
                'refund_motif' => 'Le motif du remboursement est obligatoire.',
            ]);
        }

        $payment->update([
            'status' => PaymentStatus::Rembourse,
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
            'refund_motif' => $motif,
            'refunded_by' => $actor->id,
        ]);

        Log::info('campus.payment.refunded', [
            'payment_id' => $payment->id,
            'cohort_id' => $payment->cohort_id,
            'learner_id' => $payment->learner_id,
            'amount' => $payment->amount,
            'refund_amount' => $refundAmount,
            'refund_motif' => $motif,
            'refunded_by' => $actor->id,
        ]);

        return $payment->fresh();
    }
}
