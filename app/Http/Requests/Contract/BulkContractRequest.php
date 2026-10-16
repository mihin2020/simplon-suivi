<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A selection of contracts for a grouped action (download, delete, email).
 * Each action authorizes the contracts themselves in its controller.
 */
class BulkContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Sélectionnez au moins un contrat.',
            'ids.max' => 'Vous ne pouvez pas traiter plus de 200 contrats à la fois.',
        ];
    }
}
