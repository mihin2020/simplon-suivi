<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;

class SelectFormResponsesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('select', $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        return [
            'response_ids' => ['required', 'array', 'min:1'],
            'response_ids.*' => ['uuid', 'exists:form_responses,id'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'response_ids.required' => 'Sélectionnez au moins une candidature.',
        ];
    }
}
