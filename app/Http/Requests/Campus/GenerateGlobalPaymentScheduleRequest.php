<?php

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class GenerateGlobalPaymentScheduleRequest extends FormRequest
{
    /** The route already requires a campus.finance.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.type' => ['required', 'in:amount,percentage'],
            'installments.*.value' => ['required', 'numeric', 'min:1'],
            'installments.*.due_date' => ['nullable', 'date'],
        ];
    }
}
