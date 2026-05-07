<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $isTrainer = $this->input('role') === UserRole::Trainer->value;

        return [
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'role'          => ['required', Rule::enum(UserRole::class)->only([UserRole::Admin, UserRole::Trainer])],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'profile_id'    => $isTrainer ? ['nullable', 'uuid', 'exists:trainer_profiles,id'] : ['sometimes'],
            'phone'         => $isTrainer ? ['nullable', 'string', 'max:20'] : ['sometimes'],
            'phone2'        => ['nullable', 'string', 'max:20'],
            'cv'            => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}
