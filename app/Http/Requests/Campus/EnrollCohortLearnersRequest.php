<?php

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class EnrollCohortLearnersRequest extends FormRequest
{
    /** The route already requires a campus.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learner_ids' => ['required', 'array'],
            'learner_ids.*' => ['uuid', 'exists:learners,id'],
        ];
    }
}
