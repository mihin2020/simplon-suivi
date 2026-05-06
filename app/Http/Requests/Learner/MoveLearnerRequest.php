<?php

namespace App\Http\Requests\Learner;

use Illuminate\Foundation\Http\FormRequest;

class MoveLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('move', $this->route('learner'));
    }

    public function rules(): array
    {
        return [
            'source_formation_id' => ['required', 'uuid', 'exists:formations,id'],
            'target_formation_id' => ['required', 'uuid', 'exists:formations,id', 'different:source_formation_id'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }
}
