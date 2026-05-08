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
            'profile_id.exists'   => 'Le profil sélectionné est invalide.',
            'cv.file'             => 'Le CV doit être un fichier.',
            'cv.mimes'            => 'Le CV doit être au format PDF, DOC ou DOCX.',
            'cv.max'              => 'Le CV ne peut pas dépasser 5 Mo.',
        ];
    }
}
