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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'learner_ids' => ['required', 'array', 'min:1', 'max:200'],
            'learner_ids.*' => ['uuid', 'distinct', 'exists:learners,id'],
        ];
    }
}
