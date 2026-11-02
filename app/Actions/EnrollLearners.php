<?php

namespace App\Actions;

use App\Enums\LearnerStatus;
use App\Models\Formation;
use Illuminate\Validation\ValidationException;

class EnrollLearners
{
    public function __construct(private readonly EnrollLearner $enrollLearner) {}

    /**
     * Enrolls several existing learners at once: either all of them join the formation or none does.
     *
     * @param  array<int, string>  $learnerIds
     * @return int The number of learners enrolled.
     *
     * @throws ValidationException
     */
    public function execute(Formation $formation, array $learnerIds): int
    {
        $learnerIds = array_values(array_unique($learnerIds));

        $this->enrollLearner->ensureEnrollable($formation);

        $alreadyEnrolled = $formation->learners()->whereIn('learners.id', $learnerIds)->count();

        if ($alreadyEnrolled > 0) {
            throw ValidationException::withMessages([
                'learner_ids' => $alreadyEnrolled === 1
                    ? 'Un des apprenants sélectionnés est déjà inscrit à cette formation.'
                    : "{$alreadyEnrolled} des apprenants sélectionnés sont déjà inscrits à cette formation.",
            ]);
        }

        $this->enrollLearner->ensureHasRoomFor($formation, count($learnerIds));

        $formation->learners()->attach(
            collect($learnerIds)->mapWithKeys(fn (string $id) => [
                $id => ['status' => LearnerStatus::InProgress->value, 'enrolled_at' => now()],
            ])->all()
        );

        return count($learnerIds);
    }
}
