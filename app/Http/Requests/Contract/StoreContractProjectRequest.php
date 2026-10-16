<?php

namespace App\Http\Requests\Contract;

use App\Enums\ContractTarget;
use App\Models\ContractProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ContractProject::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target' => ['required', Rule::enum(ContractTarget::class)],
        ];
    }
}
