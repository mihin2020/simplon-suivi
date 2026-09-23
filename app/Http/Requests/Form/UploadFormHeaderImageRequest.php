<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;

class UploadFormHeaderImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('form')) ?? false;
    }

    public function rules(): array
    {
        return [
            'header_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'header_image.required' => 'Choisissez une image d’en-tête.',
            'header_image.image' => 'Le fichier doit être une image.',
            'header_image.mimes' => 'Formats acceptés : JPG, PNG, WEBP.',
            'header_image.max' => 'L’image ne doit pas dépasser 4 Mo.',
        ];
    }
}
