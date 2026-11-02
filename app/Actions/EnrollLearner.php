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
    /**
     * Nobody joins a formation of a completed or archived project, nor an archived formation. Checked on its
     * own so a caller can refuse before creating anything.
     *
     * @throws ValidationException
     */
    public function ensureEnrollable(Formation $formation): void
    {
        $formation->loadMissing('project');

        if (in_array($formation->project?->status, [ProjectStatus::Completed, ProjectStatus::Archived], true)) {
            throw ValidationException::withMessages([
                'formation_id' => "Le projet de cette formation est terminé/archivé. Aucune inscription n'est possible.",
            ]);
        }

        if ($formation->status === FormationStatus::Archived) {
            throw ValidationException::withMessages([
                'formation_id' => "Cette formation est archivée. Aucune inscription n'est possible.",
            ]);
        }
    }

    /**
     * Enrolls a learner in a formation that is open to new learners.
     *
     * @throws ValidationException
     */
    public function execute(Formation $formation, Learner $learner): void
    {
        $this->ensureEnrollable($formation);

        if ($formation->learners()->where('learner_id', $learner->id)->exists()) {
            throw ValidationException::withMessages([
                'learner_id' => 'Cet apprenant est déjà inscrit à cette formation.',
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
            'status' => LearnerStatus::InProgress->value,
            'enrolled_at' => now(),
        ]);
    }
}
