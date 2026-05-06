<?php

namespace App\Http\Requests\Formation;

use App\Enums\FormationStatus;
use App\Models\Formation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Formation::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'started_at'  => ['required', 'date'],
            'ended_at'    => ['nullable', 'date', 'after:started_at'],
            'status'      => ['required', Rule::enum(FormationStatus::class)],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'location'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
