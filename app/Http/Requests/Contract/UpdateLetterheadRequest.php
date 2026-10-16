<?php

namespace App\Http\Requests\Contract;

class UpdateLetterheadRequest extends StoreLetterheadRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('letterhead'));
    }

    /**
     * Images are optional here: an omitted one keeps the current image.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'header_image' => $this->imageRules(),
            'footer_image' => $this->imageRules(),
        ];
    }
}
