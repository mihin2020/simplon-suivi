<?php

namespace App\Actions;

use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Validation\ValidationException;

class WithdrawLearner
{
    public function execute(Formation $formation, Learner $learner, ?string $notes = null): void
    {
        $pivot = $formation->learners()
            ->where('learner_id', $learner->id)
            ->wherePivot('status', LearnerStatus::InProgress->value)
            ->first();

        if ($pivot === null) {
            throw ValidationException::withMessages([
                'learner_id' => "Cet apprenant n'est pas actif dans cette formation.",
            ]);
        }

        $formation->learners()->updateExistingPivot($learner->id, [
            'status'       => LearnerStatus::Withdrawn->value,
            'withdrawn_at' => now(),
            'notes'        => $notes,
        ]);
    }
}
