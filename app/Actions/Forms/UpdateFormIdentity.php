<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Services\Forms\FormSchemaService;

class UpdateFormIdentity
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    /**
     * @param  array{title: string, description?: string|null}  $data
     */
    public function execute(Form $form, array $data): Form
    {
        $form->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        $this->schemaService->forget($form);

        return $form->fresh();
    }
}
