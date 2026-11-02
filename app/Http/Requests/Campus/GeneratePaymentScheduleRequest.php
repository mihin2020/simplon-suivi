<?php

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePaymentScheduleRequest extends FormRequest
{
    /** The route already requires a campus.finance.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.amount' => ['required', 'integer', 'min:1'],
            'installments.*.due_date' => ['nullable', 'date'],
        ];
    }
}
