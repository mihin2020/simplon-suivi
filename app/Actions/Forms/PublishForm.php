<?php

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Services\Forms\FormSchemaService;
use Illuminate\Validation\ValidationException;

class PublishForm
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    public function execute(Form $form): Form
    {
        if ($form->fields()->where('type', '!=', 'section_header')->count() === 0) {
            throw ValidationException::withMessages([
                'fields' => 'Ajoutez au moins une question avant de publier.',
            ]);
        }

        $payload = [
            'status' => FormStatus::Published,
            'published_at' => $form->published_at ?? now(),
            'closed_at' => null,
            'locked_at' => null,
            'settings' => array_merge($form->settings ?? Form::defaultSettings(), [
                'accept_responses' => true,
            ]),
        ];

        if ($form->hasLongPublicToken()) {
            $this->schemaService->forgetToken($form->public_token);
            $payload['public_token'] = Form::generatePublicToken();
        }

        $form->update($payload);

        $this->schemaService->forget($form);

        return $form->fresh(['fields', 'project', 'formation']);
    }
}
