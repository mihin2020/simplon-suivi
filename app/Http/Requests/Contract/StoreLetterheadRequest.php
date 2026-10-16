<?php

namespace App\Http\Requests\Contract;

use App\Models\Letterhead;
use Illuminate\Foundation\Http\FormRequest;

class StoreLetterheadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Letterhead::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'header_image' => ['required_without:footer_image', ...$this->imageRules()],
            'footer_image' => ['required_without:header_image', ...$this->imageRules()],
        ];
    }

    /**
     * Real MIME sniffing, not the client-supplied extension.
     *
     * @return array<int, string>
     */
    protected function imageRules(): array
    {
        return ['nullable', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp', 'mimes:png,jpg,jpeg,webp'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $format = "L'image doit être au format PNG, JPG ou WebP.";
        $size = "L'image ne doit pas dépasser 2 Mo.";
        $required = 'Importez au moins un en-tête ou un pied de page.';

        return [
            'header_image.required_without' => $required,
            'footer_image.required_without' => $required,
            'header_image.mimetypes' => $format,
            'header_image.mimes' => $format,
            'footer_image.mimetypes' => $format,
            'footer_image.mimes' => $format,
            'header_image.max' => $size,
            'footer_image.max' => $size,
        ];
    }
}
