<?php

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('backup.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'auto_enabled' => ['required', 'boolean'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'run_at' => ['required', 'date_format:H:i'],
            'retention_count' => ['required', 'integer', 'min:1', 'max:90'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'remote_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'interval_days.required' => 'Indiquez tous les combien de jours lancer la sauvegarde.',
            'interval_days.min' => 'L\'intervalle minimum est de 1 jour.',
            'interval_days.max' => 'L\'intervalle maximum est de 365 jours.',
            'run_at.date_format' => 'L\'heure doit être au format HH:MM.',
            'notify_email.email' => 'L\'adresse e-mail de notification est invalide.',
        ];
    }
}
