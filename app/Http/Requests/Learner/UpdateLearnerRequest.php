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
            'age_range_id'                => ['nullable', 'integer', 'exists:age_ranges,id'],
            'organization'                => ['nullable', 'string', 'max:255'],
            'talent'                      => ['nullable', 'string', 'max:255'],
            'emergency_contact_name'      => ['nullable', 'string', 'max:255'],
            'emergency_contact_firstname' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone'     => ['nullable', 'string', 'max:20'],
            'address'                       => ['nullable', 'string', 'max:500'],
            'location'                      => ['nullable', 'string', 'max:255'],
            'profile'                       => ['nullable', 'string', 'max:255'],
            'study_field'                   => ['nullable', 'string', 'max:255'],
            'photo'                         => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'cnib'                          => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:5120'],
            'cnib_number'                   => ['nullable', 'string', 'max:50', Rule::unique('learners', 'cnib_number')->ignore($this->route('learner'))],
            'marital_status'                => ['nullable', 'in:single,married,divorced,widowed'],
            'children_count'                => ['nullable', 'integer', 'min:0', 'max:20'],
            'vulnerability_id'              => ['nullable', 'string', 'uuid', 'exists:vulnerabilities,id'],
            'last_diploma_id'               => ['nullable', 'string', 'uuid', 'exists:last_diplomas,id'],
            'cv'                            => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}
