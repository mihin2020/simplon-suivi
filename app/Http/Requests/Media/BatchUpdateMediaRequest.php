<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('formation'));
    }

    public function rules(): array
    {
        return [
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['string', 'exists:medias,id'],
            'album' => ['required', 'string', 'max:255'],
        ];
    }
}
