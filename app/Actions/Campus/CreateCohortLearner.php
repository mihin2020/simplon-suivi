<?php

namespace App\Actions\Campus;

use App\Enums\CohortLearnerStatus;
use App\Models\Cohort;
use App\Models\Learner;
use Illuminate\Http\UploadedFile;

class CreateCohortLearner
{
    public function __construct(private readonly ApplyFormationPaymentPlanToLearner $applyPlan) {}

    /**
     * Enrolls a learner in the cohort. When the e-mail already belongs to a learner, that learner is enrolled
     * instead of creating a duplicate, and the payment plan is applied only if they were not already enrolled.
     *
     * @param  array<string, mixed>  $data  Validated learner fields
     * @return array{learner: Learner, created: bool}
     */
    public function execute(Cohort $cohort, array $data, ?UploadedFile $photo = null): array
    {
        unset($data['photo']);

        if (! empty($data['email']) && $existing = Learner::where('email', $data['email'])->first()) {
            $wasEnrolled = $cohort->learners()->where('learners.id', $existing->id)->exists();

            $this->enroll($cohort, $existing);

            if (! $wasEnrolled) {
                $this->applyPlan->execute($cohort, $existing);
            }

            return ['learner' => $existing, 'created' => false];
        }

        if ($photo) {
            $data['photo_path'] = $photo->store('learners/photos', 'public');
        }

        $learner = Learner::create($data);

        $this->enroll($cohort, $learner);
        $this->applyPlan->execute($cohort, $learner);

        return ['learner' => $learner, 'created' => true];
    }

    private function enroll(Cohort $cohort, Learner $learner): void
    {
        $cohort->learners()->syncWithoutDetaching([
            $learner->id => ['enrolled_at' => now(), 'status' => CohortLearnerStatus::Actif->value],
        ]);
    }
}
