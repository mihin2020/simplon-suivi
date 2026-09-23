<?php

namespace App\Actions\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Forms\FormSchemaService;
use App\Support\LearnerFormAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncFormFields
{
    public function __construct(private readonly FormSchemaService $schemaService) {}

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function execute(Form $form, array $fields): Form
    {
        if (! $form->status->allowsStructureEdit()) {
            throw ValidationException::withMessages([
                'fields' => 'Ce formulaire est verrouillé : les questions ne peuvent plus être modifiées.',
            ]);
        }

        DB::transaction(function () use ($form, $fields) {
            $keptIds = [];

            foreach (array_values($fields) as $index => $payload) {
                $type = FormFieldType::from($payload['type']);
                $learnerAttribute = $payload['learner_attribute'] ?? null;

                if ($learnerAttribute && ! LearnerFormAttributes::find($learnerAttribute)) {
                    throw ValidationException::withMessages([
                        "fields.{$index}.learner_attribute" => 'Attribut apprenant invalide.',
                    ]);
                }

                $attributes = [
                    'type' => $type,
                    'label' => $payload['label'],
                    'help_text' => $payload['help_text'] ?? null,
                    'is_required' => (bool) ($payload['is_required'] ?? false),
                    'position' => $index,
                    'learner_attribute' => $learnerAttribute,
                    'options' => $payload['options'] ?? null,
                    'validation' => $payload['validation'] ?? null,
                    'settings' => $payload['settings'] ?? null,
                ];

                if (! empty($payload['id'])) {
                    $field = FormField::query()
                        ->where('form_id', $form->id)
                        ->where('id', $payload['id'])
                        ->firstOrFail();
                    $field->update($attributes);
                    $keptIds[] = $field->id;
                } else {
                    $field = $form->fields()->create($attributes);
                    $keptIds[] = $field->id;
                }
            }

            $form->fields()
                ->whereNotIn('id', $keptIds)
                ->whereDoesntHave('answers')
                ->delete();

            // Keep answered fields that were removed from the editor (orphan legacy).
            $form->fields()
                ->whereNotIn('id', $keptIds)
                ->whereHas('answers')
                ->update(['position' => 9999]);
        });

        $this->schemaService->forget($form);

        return $form->fresh(['fields', 'project', 'formation']);
    }
}
