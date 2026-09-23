<?php

namespace App\Actions\Campus;

use App\Enums\PaymentStatus;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbandonCohortLearner
{
    public function execute(Cohort $cohort, Learner $learner, string $motif): void
    {
        $motif = trim($motif);
        if ($motif === '') {
            throw ValidationException::withMessages([
                'abandon_motif' => 'Le motif de l\'abandon est obligatoire.',
            ]);
        }

        $pivot = DB::table('cohort_learner')
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->first();

        if (! $pivot) {
            throw ValidationException::withMessages([
                'learner' => 'Cet apprenant n\'est pas inscrit dans cette cohorte.',
            ]);
        }

        if ($pivot->status !== 'actif') {
            throw ValidationException::withMessages([
                'learner' => 'Seul un apprenant actif peut être marqué comme abandonné.',
            ]);
        }

        DB::transaction(function () use ($cohort, $learner, $motif) {
            DB::table('cohort_learner')
                ->where('cohort_id', $cohort->id)
                ->where('learner_id', $learner->id)
                ->update([
                    'status' => 'retrait',
                    'abandon_motif' => $motif,
                    'abandoned_at' => now(),
                ]);

            // Pending installments are cancelled — paid ones stay (no auto-refund).
            Payment::query()
                ->where('cohort_id', $cohort->id)
                ->where('learner_id', $learner->id)
                ->whereIn('status', [
                    PaymentStatus::EnAttente->value,
                    PaymentStatus::EnRetard->value,
                ])
                ->update(['status' => PaymentStatus::Annule->value]);
        });
    }
}
