<?php

namespace App\Services\Campus;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

/** The numbered receipt of a collected payment, as shown on screen and in the PDF. */
class PaymentReceipt
{
    private const METHOD_LABELS = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile Money',
    ];

    /** e.g. REC-202611-0A1B2C3D: month of collection, then the start of the payment id. */
    public function number(Payment $payment): string
    {
        return 'REC-'.($payment->paid_at ?? now())->format('Ym').'-'.strtoupper(substr($payment->id, 0, 8));
    }

    /** @return array<string, mixed> */
    public function viewData(Payment $payment, ?User $issuedBy): array
    {
        $payment->load(['learner', 'cohort']);
        $payment->cohort->load(['campusFormation' => fn ($query) => $query->withTrashed()]);

        $cohort = $payment->cohort;
        $formation = $cohort->campusFormation;
        $learner = $payment->learner;
        $totalCost = $formation->total_cost ?? 0;

        $totalPaid = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->where('status', PaymentStatus::Paye->value)
            ->sum('amount');

        return [
            'payment' => $payment,
            'learner' => $learner,
            'cohort' => $cohort,
            'formation' => $formation,
            'totalCost' => $totalCost,
            'totalPaid' => $totalPaid,
            'remaining' => max(0, $totalCost - $totalPaid),
            'methodLabel' => self::METHOD_LABELS[$payment->payment_method->value ?? ''] ?? '-',
            'paidAt' => $payment->paid_at?->format('d/m/Y') ?? '-',
            'receiptNumber' => $this->number($payment),
            'issuedAt' => now()->format('d/m/Y à H:i'),
            'issuedBy' => $issuedBy ? trim("{$issuedBy->first_name} {$issuedBy->last_name}") : null,
        ];
    }
}
