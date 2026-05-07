<?php

namespace App\Http\Requests\Trainer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trainer'));
    }

    public function rules(): array
    {
        return [
            'profile_id' => ['nullable', 'uuid', 'exists:trainer_profiles,id'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'phone2'     => ['nullable', 'string', 'max:20'],
            'cv'         => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'remove_cv'  => ['nullable', 'boolean'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
