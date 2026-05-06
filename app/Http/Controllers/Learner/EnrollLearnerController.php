<?php

namespace App\Http\Controllers\Learner;

use App\Actions\EnrollLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\EnrollLearnerRequest;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;

class EnrollLearnerController extends Controller
{
    public function __invoke(EnrollLearnerRequest $request, Formation $formation, EnrollLearner $action): RedirectResponse
    {
        $learner = Learner::findOrFail($request->validated('learner_id'));

        $action->execute($formation, $learner);

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', "{$learner->full_name} a été inscrit(e) à la formation.");
    }
}
