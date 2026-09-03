<?php

namespace App\Actions;

use App\Models\Learner;
use App\Models\LearnerInterview;
use App\Models\User;
use App\Support\InterviewCustomFields;

class CreateLearnerInterview
{
    public function execute(Learner $learner, array $data, User $actor): LearnerInterview
    {
        $meta = InterviewCustomFields::toMeta($data['custom_fields'] ?? null);
        unset($data['custom_fields']);

        return $learner->interviews()->create([
            ...$data,
            'conducted_by' => $data['conducted_by'] ?? $actor->id,
            'created_by' => $actor->id,
            'is_important' => $data['is_important'] ?? false,
            'meta' => $meta,
        ]);
    }
}
