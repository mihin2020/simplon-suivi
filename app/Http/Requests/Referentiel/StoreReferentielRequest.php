<?php

namespace App\Http\Requests\Referentiel;

use App\Models\Referentiel;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferentielRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Referentiel::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
