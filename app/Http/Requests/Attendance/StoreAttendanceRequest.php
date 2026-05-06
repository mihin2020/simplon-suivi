<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceCode;
use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attendance::class, $this->route('formation')]);
    }

    public function rules(): array
    {
        return [
            'date'    => ['required', 'date'],
            'records' => ['required', 'array'],
            'records.*' => ['required', 'string', Rule::in(array_column(AttendanceCode::cases(), 'value'))],
        ];
    }
}
