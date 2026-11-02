<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class ReplyEmailRequest extends FormRequest
{
    /** The route already requires the communication.send / communication.manage permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
