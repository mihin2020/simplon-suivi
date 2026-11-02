<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class SyncProjectPartnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['uuid', 'exists:partners,id'],
        ];
    }
}
