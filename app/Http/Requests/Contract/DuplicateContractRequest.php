<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class DuplicateContractRequest extends FormRequest
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
            // One copy of the contract is generated per recipient.
            'subject_ids' => ['required', 'array', 'min:1', 'max:200'],
            'subject_ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_ids.required' => 'Sélectionnez au moins une personne.',
            'subject_ids.min' => 'Sélectionnez au moins une personne.',
            'subject_ids.max' => 'Vous ne pouvez pas générer plus de 200 contrats à la fois.',
        ];
    }
}
