<?php

namespace App\Http\Requests\Referentiel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReferentielRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('referentiel'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'blocks' => ['nullable', 'array'],
            'blocks.*.id' => ['nullable', 'uuid'],
            'blocks.*.name' => ['required', 'string', 'max:255'],
            'blocks.*.competences' => ['nullable', 'array'],
            'blocks.*.competences.*.id' => ['nullable', 'uuid'],
            'blocks.*.competences.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
