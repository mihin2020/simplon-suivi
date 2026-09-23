<?php

namespace App\Services\Forms;

use App\Enums\UserRole;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Notification;
use App\Models\User;

class NotifyFormResponseSubmitted
{
    public function execute(Form $form, FormResponse $response): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', UserRole::SuperAdmin)
                    ->orWhereHas('permissions', fn ($p) => $p->where('slug', 'forms.responses'));
            })
            ->get(['id']);

        if ($recipients->isEmpty()) {
            return;
        }

        $email = $response->email ?: 'candidat';
        $title = 'Nouvelle candidature';
        $message = "« {$form->title} » — {$email}";

        foreach ($recipients as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'form_response_submitted',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'form_id' => $form->id,
                    'response_id' => $response->id,
                    'route' => route('forms.responses.index', $form),
                ],
            ]);
        }
    }
}
