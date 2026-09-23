<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Services\Forms\FormSchemaService;

class ShortenFormPublicLink
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    public function execute(Form $form): Form
    {
        if (! $form->hasLongPublicToken()) {
            return $form;
        }

        $oldToken = $form->public_token;
        $this->schemaService->forgetToken($oldToken);

        $form->update([
            'public_token' => Form::generatePublicToken(),
        ]);

        $this->schemaService->forget($form);

        return $form->fresh(['fields', 'project', 'formation']);
    }
}
