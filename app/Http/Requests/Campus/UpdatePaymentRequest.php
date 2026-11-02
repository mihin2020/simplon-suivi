<?php

namespace App\Http\Requests\Campus;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('correct', Payment::class);
    }

    public function rules(): array
    {
        $isPaid = $this->route('payment')->status === PaymentStatus::Paye;

        return [
            'amount' => ['required', 'integer', 'min:1'],
            'due_date' => ['required', 'date'],
            // The collection date only exists (and is required) for a paid installment.
            'paid_at' => [$isPaid ? 'required' : 'nullable', 'date'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Seul un administrateur peut corriger une tranche.');
    }
}
