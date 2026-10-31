<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attendance::class, $this->route('formation')]);
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'extra' => ['nullable', 'boolean'],
        ];
    }
}
