<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class ForwardEmailRequest extends FormRequest
{
    /** The route already requires the communication.send / communication.manage permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*.email' => ['required', 'email'],
            'to.*.name' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
        ];
    }
}
