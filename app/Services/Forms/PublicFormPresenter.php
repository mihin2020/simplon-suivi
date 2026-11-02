<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormField;

/** Formats a form for the public page where it is filled in. */
class PublicFormPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function form(Form $form): array
    {
        $isExpired = $form->hasExpired();

        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'header_image_url' => $form->headerImageUrl(),
            'status' => $form->status->value,
            'status_label' => $form->status->label(),
            'accepts_responses' => ! $isExpired && $form->acceptsResponses(),
            'is_expired' => $isExpired,
            'project_name' => $form->project?->name,
            'formation_name' => $form->formation?->name,
            'confirmation_message' => $form->settings['confirmation_message'] ?? null,
            'show_progress' => (bool) ($form->settings['show_progress'] ?? true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(Form $form): array
    {
        return $form->fields->values()->map(fn (FormField $field) => [
            'id' => $field->id,
            'type' => $field->type->value,
            'label' => $field->label,
            'help_text' => $field->help_text,
            'is_required' => $field->is_required,
            'options' => $field->options,
            'settings' => $field->settings,
            'learner_attribute' => $field->learner_attribute,
            'is_answerable' => $field->type->isAnswerable(),
        ])->all();
    }
}
