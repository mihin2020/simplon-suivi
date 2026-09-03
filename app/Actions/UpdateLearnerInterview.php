<?php

namespace App\Actions;

use App\Models\LearnerInterview;
use App\Support\InterviewCustomFields;

class UpdateLearnerInterview
{
    public function execute(LearnerInterview $interview, array $data): LearnerInterview
    {
        $data['meta'] = InterviewCustomFields::toMeta($data['custom_fields'] ?? null);
        unset($data['custom_fields']);

        $interview->update($data);

        return $interview->fresh(['conductor']);
    }
}
