<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'started_at'  => ['required', 'date'],
            'ended_at'    => ['nullable', 'date', 'after:started_at'],
            'status'      => ['required', Rule::enum(ProjectStatus::class)],
        ];
    }
}
