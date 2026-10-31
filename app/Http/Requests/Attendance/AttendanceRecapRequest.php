<?php

namespace App\Http\Requests\Attendance;

use App\Enums\RecapView;
use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRecapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attendance::class, $this->route('formation')]);
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::enum(RecapView::class)],
            'anchor' => ['nullable', 'date'],
        ];
    }

    public function recapView(RecapView $default): RecapView
    {
        return RecapView::tryFrom((string) $this->validated('view')) ?? $default;
    }
}
