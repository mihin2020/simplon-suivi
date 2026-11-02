<?php

namespace App\Http\Requests\Campus;

use Illuminate\Validation\Rule;

class UpdateCohortLearnerRequest extends StoreCohortLearnerRequest
{
    public function rules(): array
    {
        return [
            ...$this->learnerRules(),
            'email' => ['nullable', 'email', 'max:255', Rule::unique('learners', 'email')->ignore($this->route('learner'))],
        ];
    }
}
