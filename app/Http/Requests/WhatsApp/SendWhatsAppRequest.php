<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppRequest extends FormRequest
{
    /** The route already requires the whatsapp.send / whatsapp.manage permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipients' => 'required|array|min:1',
            'recipients.*.phone' => 'required|string',
            'recipients.*.name' => 'nullable|string',
            'recipients.*.learner_id' => 'nullable|uuid|exists:learners,id',
            'message' => 'nullable|string|max:4096',
            'formation_name' => 'nullable|string|max:255',
            'project_name' => 'nullable|string|max:255',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,mp4,mp3,ogg|max:16384',
        ];
    }
}
