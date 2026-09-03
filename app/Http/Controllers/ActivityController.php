<?php

namespace App\Http\Controllers;

use App\Actions\CreateActivity;
use App\Actions\DeleteActivity;
use App\Actions\UpdateActivity;
use App\Http\Requests\Planning\StoreActivityRequest;
use App\Http\Requests\Planning\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Phase;
use Illuminate\Http\RedirectResponse;

class ActivityController extends Controller
{
    public function store(StoreActivityRequest $request, Phase $phase, CreateActivity $action): RedirectResponse
    {
        $action->execute($phase, $request->validated(), $request->user());

        return back()->with('success', 'Activité créée avec succès.');
    }

    public function update(UpdateActivityRequest $request, Activity $activity, UpdateActivity $action): RedirectResponse
    {
        $action->execute($activity, $request->validated(), $request->user());

        return back()->with('success', 'Activité mise à jour.');
    }

    public function destroy(Activity $activity, DeleteActivity $action): RedirectResponse
    {
        $activity->loadMissing('phase.project');
        $this->authorize('update', $activity->phase->project);

        $action->execute($activity);

        return back()->with('success', 'Activité supprimée.');
    }
}
