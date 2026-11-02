<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailRequest extends FormRequest
{
    /** The route already requires the communication.send / communication.manage permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1', 'max:300'],
            'to.*.email' => ['required', 'email'],
            'to.*.name' => ['nullable', 'string'],
            'cc' => ['nullable', 'array'],
            'cc.*.email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
