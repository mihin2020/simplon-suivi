<?php

namespace App\Http\Requests\Contract;

use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\ContractTarget;
use App\Enums\LetterheadScope;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
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
            // Content validated in the wizard (imported file or AI draft).
            'target' => ['required', Rule::enum(ContractTarget::class)],
            'name' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:500000'],
            'signatories' => ['nullable', 'array'],
            'signatories.left_title' => ['nullable', 'string', 'max:120'],
            'signatories.left_name' => ['nullable', 'string', 'max:120'],
            'signatories.right_title' => ['nullable', 'string', 'max:120'],
            'signatories.right_name' => ['nullable', 'string', 'max:120'],
            'letterhead_id' => ['nullable', 'uuid', Rule::exists('letterheads', 'id')->whereNull('deleted_at')],
            'letterhead_scope' => ['nullable', Rule::enum(LetterheadScope::class)],
            'font' => ['nullable', Rule::enum(ContractFont::class)],
            'line_spacing' => ['nullable', Rule::enum(ContractLineSpacing::class)],
            'subject_id' => ['nullable', 'uuid'],
            'contract_project_id' => ['nullable', 'uuid', Rule::exists('contract_projects', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Donnez un nom au contrat.',
            'body_html.required' => 'Le contenu du contrat est vide.',
        ];
    }
}
