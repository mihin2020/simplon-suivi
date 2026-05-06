<?php

namespace App\Http\Requests\Learner;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('learner'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
