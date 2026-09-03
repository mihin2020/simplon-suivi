<?php

namespace App\Http\Controllers;

use App\Actions\CreatePhase;
use App\Actions\DeletePhase;
use App\Actions\UpdatePhase;
use App\Http\Requests\Planning\StorePhaseRequest;
use App\Http\Requests\Planning\UpdatePhaseRequest;
use App\Models\Phase;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class PhaseController extends Controller
{
    public function store(StorePhaseRequest $request, Project $project, CreatePhase $action): RedirectResponse
    {
        $action->execute($project, $request->validated(), $request->user());

        return back()->with('success', 'Phase créée avec succès.');
    }

    public function update(UpdatePhaseRequest $request, Phase $phase, UpdatePhase $action): RedirectResponse
    {
        $action->execute($phase, $request->validated(), $request->user());

        return back()->with('success', 'Phase mise à jour.');
    }

    public function destroy(Phase $phase, DeletePhase $action): RedirectResponse
    {
        $phase->loadMissing('project');
        $this->authorize('update', $phase->project);

        $action->execute($phase);

        return back()->with('success', 'Phase supprimée.');
    }
}
