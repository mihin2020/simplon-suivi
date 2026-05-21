<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'budget'        => ['nullable', 'integer', 'min:0'],
            'started_at'    => ['required', 'date'],
            'ended_at'      => ['nullable', 'date', 'after:started_at'],
            'status'        => ['required', Rule::enum(ProjectStatus::class)],
            'partner_ids'   => ['nullable', 'array'],
            'partner_ids.*' => ['uuid', 'exists:partners,id'],
        ];
    }
}
