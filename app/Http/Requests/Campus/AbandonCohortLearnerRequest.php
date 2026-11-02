<?php

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class AbandonCohortLearnerRequest extends FormRequest
{
    /** The route already requires a campus.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'abandon_motif' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'abandon_motif.required' => "Le motif de l'abandon est obligatoire.",
            'abandon_motif.max' => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
