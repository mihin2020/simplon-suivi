<?php

namespace App\Http\Requests\Form;

use App\Enums\FormResponseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormResponsesFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewResponses', $this->route('form'));
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(FormResponseStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
