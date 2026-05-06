<?php

namespace App\Actions;

use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Validation\ValidationException;

class EnrollLearner
{
    public function execute(Formation $formation, Learner $learner): void
    {
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
