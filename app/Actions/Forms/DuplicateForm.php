<?php

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DuplicateForm
{
    public function execute(Form $source, User $user): Form
    {
        $source->load('fields');

        return DB::transaction(function () use ($source, $user) {
            $copy = Form::create([
                'project_id' => $source->project_id,
                'formation_id' => $source->formation_id,
                'created_by' => $user->id,
                'title' => $source->title.' (copie)',
                'description' => $source->description,
                'status' => FormStatus::Draft,
                'public_token' => Form::generatePublicToken(),
                'settings' => $source->settings ?? Form::defaultSettings(),
            ]);

            foreach ($source->fields as $field) {
                $copy->fields()->create([
                    'type' => $field->type,
                    'label' => $field->label,
                    'help_text' => $field->help_text,
                    'is_required' => $field->is_required,
                    'position' => $field->position,
                    'learner_attribute' => $field->learner_attribute,
                    'options' => $field->options,
                    'validation' => $field->validation,
                    'settings' => $field->settings,
                ]);
            }

            return $copy->load(['fields', 'project', 'formation']);
        });
    }
}
