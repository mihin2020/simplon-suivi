<?php

namespace App\Http\Requests\Learner;

use App\Models\Learner;
use Illuminate\Foundation\Http\FormRequest;

class EnrollLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Learner::class);
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
        ];
    }
}
