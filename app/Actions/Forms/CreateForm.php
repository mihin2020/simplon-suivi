<?php

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Enums\Gender;
use App\Models\Form;
use App\Models\Formation;
use App\Models\User;
use App\Support\LearnerFormAttributes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateForm
{
    public function execute(User $user, array $data): Form
    {
        $formation = Formation::query()
            ->with('project')
            ->findOrFail($data['formation_id']);

        if ($formation->project_id !== $data['project_id']) {
            throw ValidationException::withMessages([
                'formation_id' => 'La formation doit appartenir au projet sélectionné.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $formation) {
            $form = Form::create([
                'project_id' => $formation->project_id,
                'formation_id' => $formation->id,
                'created_by' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => FormStatus::Draft,
                'public_token' => Form::generatePublicToken(),
                'settings' => array_merge(Form::defaultSettings(), $data['settings'] ?? []),
            ]);

            $position = 0;
            foreach (LearnerFormAttributes::defaults() as $attribute) {
                $options = null;
                if ($attribute['key'] === 'gender') {
                    $options = collect(Gender::cases())
                        ->map(fn (Gender $g) => ['label' => $g->label(), 'value' => $g->value])
                        ->values()
                        ->all();
                }

                $form->fields()->create([
                    'type' => $attribute['type']->value,
                    'label' => $attribute['label'],
                    'is_required' => $attribute['required'],
                    'position' => $position++,
                    'learner_attribute' => $attribute['key'],
                    'options' => $options,
                ]);
            }

            return $form->load(['fields', 'project', 'formation']);
        });
    }
}
