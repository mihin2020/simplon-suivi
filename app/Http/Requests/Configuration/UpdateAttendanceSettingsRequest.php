<?php

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('configuration.manage');
    }

    public function rules(): array
    {
        return [
            'absence_alert_threshold' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'absence_alert_threshold.integer' => 'Le seuil doit être un nombre entier.',
            'absence_alert_threshold.min' => 'Le seuil minimum est de 1 absence.',
            'absence_alert_threshold.max' => 'Le seuil maximum est de 999 absences.',
        ];
    }
}
