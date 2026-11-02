<?php

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class MoveCohortLearnerRequest extends FormRequest
{
    /** The route already requires a campus.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_cohort_id' => ['required', 'uuid', 'exists:cohorts,id'],
        ];
    }
}
