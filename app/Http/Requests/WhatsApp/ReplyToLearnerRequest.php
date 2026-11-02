<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class ReplyToLearnerRequest extends FormRequest
{
    /** The route already requires the whatsapp.send / whatsapp.manage permission. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
            'message' => ['nullable', 'string', 'max:4096'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,mp3,ogg,doc,docx', 'max:16384'],
        ];
    }
}
