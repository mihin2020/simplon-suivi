<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class ImportFillModelRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:2048', 'mimes:xlsx,xls,csv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez le fichier Excel rempli.',
            'file.mimes' => 'Le fichier doit être un Excel (.xlsx, .xls) ou un .csv.',
            'file.max' => 'Le fichier ne doit pas dépasser 2 Mo.',
        ];
    }
}
