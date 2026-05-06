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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'specialty'  => ['nullable', 'string', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:20'],
        ];
    }
}
