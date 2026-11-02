<?php

namespace App\Actions\Campus;

use App\Enums\CohortLearnerStatus;
use App\Enums\CohortStatus;
use App\Enums\PaymentStatus;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveCohortLearner
{
    /**
     * Moves an active learner to another open cohort. What they already paid follows them; the unpaid
     * installments were sized for the old formation, so they are cancelled (to be planned again).
     *
     * All the writes happen together or not at all.
     *
     * @return array{transferred: int, cancelled: int}
     *
     * @throws ValidationException
     */
    public function execute(Cohort $source, Learner $learner, Cohort $target): array
    {
        if ($target->id === $source->id) {
            throw ValidationException::withMessages(['target_cohort_id' => 'La cohorte cible doit être différente de la cohorte source.']);
        }

        $pivot = DB::table('cohort_learner')
            ->where('cohort_id', $source->id)
            ->where('learner_id', $learner->id)
            ->first();

        if (! $pivot || $pivot->status !== CohortLearnerStatus::Actif->value) {
            throw ValidationException::withMessages(['learner' => "Cet apprenant n'est pas actif dans cette cohorte."]);
        }

        if ($target->status === CohortStatus::Cloturee) {
            throw ValidationException::withMessages(['target_cohort_id' => 'La cohorte cible est clôturée.']);
        }

        return DB::transaction(function () use ($source, $learner, $target) {
            DB::table('cohort_learner')
                ->where('cohort_id', $source->id)
                ->where('learner_id', $learner->id)
                ->update(['status' => CohortLearnerStatus::Deplace->value]);

            // Already present in the target: keep their existing enrolment.
            DB::table('cohort_learner')->insertOrIgnore([
                'cohort_id' => $target->id,
                'learner_id' => $learner->id,
                'enrolled_at' => now(),
                'status' => CohortLearnerStatus::Actif->value,
            ]);

            $transferred = $this->payments($source, $learner)
                ->where('status', PaymentStatus::Paye->value)
                ->update(['cohort_id' => $target->id]);

            $cancelled = $this->payments($source, $learner)
                ->whereIn('status', [PaymentStatus::EnAttente->value, PaymentStatus::EnRetard->value])
                ->update(['status' => PaymentStatus::Annule->value]);

            return ['transferred' => $transferred, 'cancelled' => $cancelled];
        });
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(Cohort $cohort, Learner $learner): Builder
    {
        return Payment::where('cohort_id', $cohort->id)->where('learner_id', $learner->id);
    }
}
