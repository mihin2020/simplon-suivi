<?php

namespace App\Http\Requests\Trainer;

use App\Models\Trainer;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Trainer::class);
    }

    public function rules(): array
    {
        return [
            'last_name'  => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'profile_id' => ['nullable', 'uuid', 'exists:trainer_profiles,id'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'phone2'     => ['nullable', 'string', 'max:20'],
            'cv'         => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}
