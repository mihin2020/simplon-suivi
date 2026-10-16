<?php

namespace App\Http\Requests\Contract;

use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\LetterheadScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('contract'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'signatories' => ['nullable', 'array'],
            'signatories.left_title' => ['nullable', 'string', 'max:120'],
            'signatories.left_name' => ['nullable', 'string', 'max:120'],
            'signatories.right_title' => ['nullable', 'string', 'max:120'],
            'signatories.right_name' => ['nullable', 'string', 'max:120'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:255'],
            'letterhead_id' => ['nullable', 'uuid', Rule::exists('letterheads', 'id')->whereNull('deleted_at')],
            'letterhead_scope' => ['nullable', Rule::enum(LetterheadScope::class)],
            'font' => ['nullable', Rule::enum(ContractFont::class)],
            'line_spacing' => ['nullable', Rule::enum(ContractLineSpacing::class)],
        ];
    }
}
