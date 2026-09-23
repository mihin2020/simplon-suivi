<?php

namespace App\Http\Requests\Form;

use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Form::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'formation_id' => [
                'required',
                'uuid',
                Rule::exists('formations', 'id')->where(fn ($q) => $q->where('project_id', $this->input('project_id'))),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'project_id.required' => 'Le projet est obligatoire.',
            'formation_id.required' => 'La formation est obligatoire.',
            'formation_id.exists' => 'La formation doit appartenir au projet sélectionné.',
        ];
    }
}
