<?php

namespace App\Http\Requests\Statistics;

use App\Enums\Gender;
use App\Enums\InsertionStatus;
use App\Enums\LearnerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormationLearnersFilterRequest extends FormRequest
{
    /** The route already requires the statistics.view permission. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'status' => ['nullable', Rule::enum(LearnerStatus::class)],
            'insertion_status' => ['nullable', Rule::enum(InsertionStatus::class)],
        ];
    }
}
