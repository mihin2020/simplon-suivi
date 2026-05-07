<?php

namespace App\Http\Requests\Learner;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('learner'));
    }

    public function rules(): array
    {
        return [
            'first_name'                  => ['required', 'string', 'max:255'],
            'last_name'                   => ['required', 'string', 'max:255'],
            'email'                       => ['nullable', 'email', 'max:255', Rule::unique('learners', 'email')->ignore($this->route('learner'))],
            'phone'                       => ['nullable', 'string', 'max:20'],
            'birth_date'                  => ['nullable', 'date'],
            'birth_place'                 => ['nullable', 'string', 'max:255'],
            'gender'                      => ['nullable', Rule::enum(Gender::class)],
            'education_level_id'          => ['nullable', 'integer', 'exists:education_levels,id'],
            'talent'                      => ['nullable', 'string', 'max:255'],
            'emergency_contact_name'      => ['nullable', 'string', 'max:255'],
            'emergency_contact_firstname' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone'     => ['nullable', 'string', 'max:20'],
            'photo'                       => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'cnib'                        => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:5120'],
        ];
    }
}
