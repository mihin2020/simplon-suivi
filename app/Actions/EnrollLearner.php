<?php

namespace App\Actions;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\ProjectStatus;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Validation\ValidationException;

class EnrollLearner
{
    public function execute(Formation $formation, Learner $learner): void
    {
        // Charger le projet si nécessaire
        if (!$formation->relationLoaded('project')) {
            $formation->load('project');
        }

        // Vérifier si le projet est terminé ou archivé
        if ($formation->project && ($formation->project->status === ProjectStatus::Completed->value || $formation->project->status === ProjectStatus::Archived->value)) {
            throw ValidationException::withMessages([
                'formation_id' => "Le projet de cette formation est terminé/archivé. Aucune inscription n'est possible.",
            ]);
        }

        // Vérifier si la formation est archivée
        if ($formation->status === FormationStatus::Archived->value) {
            throw ValidationException::withMessages([
                'formation_id' => "Cette formation est archivée. Aucune inscription n'est possible.",
            ]);
        }

        if ($formation->learners()->where('learner_id', $learner->id)->exists()) {
            throw ValidationException::withMessages([
                'learner_id' => "Cet apprenant est déjà inscrit à cette formation.",
            ]);
        }

        if ($formation->capacity !== null) {
            $enrolled = $formation->activeLearners()->count();

            if ($enrolled >= $formation->capacity) {
                throw ValidationException::withMessages([
                    'formation_id' => "La formation a atteint sa capacité maximale ({$formation->capacity} apprenants).",
                ]);
            }
        }

        $formation->learners()->attach($learner->id, [
            'status'      => LearnerStatus::InProgress->value,
            'enrolled_at' => now(),
        ]);
    }
}
