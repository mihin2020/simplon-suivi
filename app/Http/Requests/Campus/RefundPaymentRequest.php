<?php

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->route('payment');
        $max = $payment?->amount ?? 1;

        return [
            'refund_amount' => ['required', 'integer', 'min:1', 'max:'.$max],
            'refund_motif' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.required' => 'Le montant du remboursement est obligatoire.',
            'refund_amount.min' => 'Le montant du remboursement doit être au moins 1 FCFA.',
            'refund_amount.max' => 'Le montant ne peut pas dépasser le montant de la tranche.',
            'refund_motif.required' => 'Le motif du remboursement est obligatoire.',
            'refund_motif.max' => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
