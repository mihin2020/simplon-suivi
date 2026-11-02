<?php

namespace App\Http\Requests\Campus;

use App\Enums\CohortStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCohortRequest extends FormRequest
{
    /** The route already requires a campus.cohorts.* permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'status' => ['required', Rule::enum(CohortStatus::class)],
        ];
    }
}
