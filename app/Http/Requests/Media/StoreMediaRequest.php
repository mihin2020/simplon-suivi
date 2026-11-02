<?php

namespace App\Http\Requests\Media;

use App\Rules\CloudinaryUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('formation'));
    }

    public function rules(): array
    {
        return [
            'cloudinary_public_id' => ['required', 'string'],
            'url' => ['required', 'string', new CloudinaryUrl],
            'thumbnail_url' => ['nullable', 'string', new CloudinaryUrl],
            'type' => ['required', 'in:photo,video'],
            'title' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer'], // KB
            'width' => ['nullable', 'integer'],
            'height' => ['nullable', 'integer'],
            'duration' => ['nullable', 'integer'], // seconds
            'format' => ['nullable', 'string', 'max:20'],
        ];
    }
}
