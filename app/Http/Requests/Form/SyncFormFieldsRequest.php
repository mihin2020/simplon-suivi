<?php

namespace App\Http\Requests\Form;

use App\Enums\FormFieldType;
use App\Support\LearnerFormAttributes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncFormFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        $types = array_map(fn (FormFieldType $t) => $t->value, FormFieldType::builderTypes());

        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.id' => ['nullable', 'uuid'],
            'fields.*.type' => ['required', Rule::in($types)],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.learner_attribute' => ['nullable', 'string', Rule::in(LearnerFormAttributes::keys())],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.label' => ['required_with:fields.*.options', 'string', 'max:255'],
            'fields.*.options.*.value' => ['required_with:fields.*.options', 'string', 'max:255'],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.settings.min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fields.*.settings.max' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fields.*.settings.min_label' => ['nullable', 'string', 'max:100'],
            'fields.*.settings.max_label' => ['nullable', 'string', 'max:100'],
            'fields.*.settings.accept' => ['nullable', 'string', 'max:255'],
            'fields.*.settings.max_size_kb' => ['nullable', 'integer', 'min:1', 'max:10240'],
            'fields.*.validation' => ['nullable', 'array'],
        ];
    }
}
