<?php

namespace App\Http\Requests\Campus;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCohortLearnerRequest extends FormRequest
{
    /** The route already requires a campus.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->learnerRules();
    }

    /** @return array<string, array<int, mixed>> */
    protected function learnerRules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date'],
            'education_level_id' => ['nullable', 'exists:education_levels,id'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_firstname' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
