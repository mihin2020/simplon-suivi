<?php

namespace App\Actions;

use App\Models\LearnerInterview;

class DeleteLearnerInterview
{
    public function execute(LearnerInterview $interview): void
    {
        $interview->delete();
    }
}
