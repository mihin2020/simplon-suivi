<?php

namespace App\Actions;

use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveLearner
{
    public function execute(Learner $learner, Formation $source, Formation $target, ?string $notes = null): void
    {
        if ($source->project_id !== $target->project_id) {
            throw ValidationException::withMessages([
                'target_formation_id' => "Le déplacement n'est autorisé qu'entre formations du même projet.",
            ]);
        }

        $isActiveInSource = $source->learners()
            ->where('learner_id', $learner->id)
            ->wherePivot('status', LearnerStatus::InProgress->value)
            ->exists();

        if (! $isActiveInSource) {
            throw ValidationException::withMessages([
                'learner_id' => "Cet apprenant n'est pas actif dans la formation source.",
            ]);
        }

        if ($target->learners()->where('learner_id', $learner->id)->exists()) {
            throw ValidationException::withMessages([
                'target_formation_id' => "Cet apprenant est déjà inscrit dans la formation cible.",
            ]);
        }

        DB::transaction(function () use ($learner, $source, $target, $notes) {
            $source->learners()->updateExistingPivot($learner->id, [
                'status'       => LearnerStatus::Moved->value,
                'withdrawn_at' => now(),
                'notes'        => $notes,
            ]);

            $target->learners()->attach($learner->id, [
                'status'      => LearnerStatus::InProgress->value,
                'enrolled_at' => now(),
            ]);
        });
    }
}
