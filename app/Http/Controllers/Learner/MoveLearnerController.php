<?php

namespace App\Http\Controllers\Learner;

use App\Actions\MoveLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\MoveLearnerRequest;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MoveLearnerController extends Controller
{
    public function create(Learner $learner): Response
    {
        $this->authorize('move', $learner);

        $learner->load(['formations.project']);

        $activeFormations = $learner->formations()
            ->wherePivot('status', 'in_progress')
            ->with('project')
            ->get();

        return Inertia::render('Learners/Move', [
            'learner'          => $learner,
            'activeFormations' => $activeFormations,
        ]);
    }

    public function store(MoveLearnerRequest $request, Learner $learner, MoveLearner $action): RedirectResponse
    {
        $source = Formation::findOrFail($request->validated('source_formation_id'));
        $target = Formation::findOrFail($request->validated('target_formation_id'));

        $action->execute($learner, $source, $target, $request->validated('notes'));

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', "{$learner->full_name} a été déplacé(e) vers \"{$target->name}\".");
    }
}
