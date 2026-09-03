<?php

namespace App\Http\Controllers;

use App\Actions\CreateLearnerInterview;
use App\Actions\DeleteLearnerInterview;
use App\Actions\UpdateLearnerInterview;
use App\Http\Requests\Interview\StoreLearnerInterviewRequest;
use App\Http\Requests\Interview\UpdateLearnerInterviewRequest;
use App\Models\Learner;
use App\Models\LearnerInterview;
use Illuminate\Http\RedirectResponse;

class LearnerInterviewController extends Controller
{
    public function store(
        StoreLearnerInterviewRequest $request,
        Learner $learner,
        CreateLearnerInterview $action,
    ): RedirectResponse {
        $action->execute($learner, $request->validated(), $request->user());

        return back()->with('success', 'Entretien ajouté à l\'historique.');
    }

    public function update(
        UpdateLearnerInterviewRequest $request,
        Learner $learner,
        LearnerInterview $interview,
        UpdateLearnerInterview $action,
    ): RedirectResponse {
        $action->execute($interview, $request->validated());

        return back()->with('success', 'Entretien mis à jour.');
    }

    public function destroy(
        Learner $learner,
        LearnerInterview $interview,
        DeleteLearnerInterview $action,
    ): RedirectResponse {
        abort_unless($interview->learner_id === $learner->id, 404);
        $this->authorize('update', $learner);

        $action->execute($interview);

        return back()->with('success', 'Entretien supprimé.');
    }
}
