<?php

namespace App\Http\Requests\Contract;

use App\Enums\ContractTarget;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DraftContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Contract::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['questions', 'draft', 'revise', 'reformat'])],
            'target' => ['required', Rule::enum(ContractTarget::class)],
            'subject_id' => ['nullable', 'uuid'],
            'prompt' => ['required_unless:action,revise', 'nullable', 'string', 'max:4000'],
            'answers' => ['nullable', 'array', 'max:10'],
            'answers.*.question' => ['required', 'string', 'max:500'],
            'answers.*.answer' => ['nullable', 'string', 'max:1000'],
            'current_html' => ['required_if:action,revise', 'required_if:action,reformat', 'nullable', 'string', 'max:200000'],
            'instruction' => ['required_if:action,revise', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required_unless' => 'Décrivez le contrat à rédiger.',
            'instruction.required_if' => 'Décrivez la modification souhaitée.',
        ];
    }
}
