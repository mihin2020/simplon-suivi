<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $user      = $this->route('user');
        $isTrainer = $this->input('role') === UserRole::Trainer->value;

        return [
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'role'          => ['required', Rule::enum(UserRole::class)->only([UserRole::Admin, UserRole::Trainer])],
            'is_active'     => ['boolean'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'profile_id'    => $isTrainer ? ['nullable', 'uuid', 'exists:trainer_profiles,id'] : ['nullable', 'prohibited'],
            'phone'         => $isTrainer ? ['nullable', 'string', 'max:20'] : ['nullable', 'prohibited'],
            'phone2'        => $isTrainer ? ['nullable', 'string', 'max:20'] : ['nullable', 'prohibited'],
            'cv'            => $isTrainer ? ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'] : ['nullable', 'prohibited'],
            'remove_cv'     => $isTrainer ? ['nullable', 'boolean'] : ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.max'      => 'Le prénom ne peut pas dépasser 255 caractères.',
            'last_name.required'  => 'Le nom est obligatoire.',
            'last_name.max'       => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required'      => 'L\'adresse email est obligatoire.',
            'email.email'         => 'L\'adresse email n\'est pas valide.',
            'email.max'           => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'email.unique'        => 'Cette adresse email est déjà utilisée.',
            'role.required'       => 'Le type de compte est obligatoire.',
            'role.enum'           => 'Le type de compte sélectionné est invalide.',
            'cv.mimes'             => 'Le CV doit être au format PDF, DOC ou DOCX.',
            'cv.max'               => 'Le CV ne doit pas dépasser 5 Mo.',
        ];
    }
}
