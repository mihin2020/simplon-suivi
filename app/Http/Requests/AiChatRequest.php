<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message'          => ['required', 'string', 'max:2000'],
            'history'          => ['nullable', 'array', 'max:20'],
            'history.*.role'   => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Le message est requis.',
            'message.max'      => 'Le message ne peut pas dépasser 2000 caractères.',
        ];
    }
}
