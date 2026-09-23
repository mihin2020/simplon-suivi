<?php

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Services\Forms\FormSchemaService;

class CloseForm
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    public function execute(Form $form): Form
    {
        $form->update([
            'status' => FormStatus::Closed,
            'closed_at' => now(),
            'settings' => array_merge($form->settings ?? Form::defaultSettings(), [
                'accept_responses' => false,
            ]),
        ]);

        $this->schemaService->forget($form);

        return $form->fresh(['fields', 'project', 'formation']);
    }
}
