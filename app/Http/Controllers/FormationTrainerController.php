<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormationTrainerController extends Controller
{
    public function store(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorize('update', $formation);

        $data = $request->validate([
            'trainer_ids'   => ['required', 'array', 'min:1'],
            'trainer_ids.*' => ['uuid', 'exists:trainers,id'],
        ]);

        $assignedIds = $formation->trainers()->pluck('trainers.id')->toArray();
        $newIds      = array_diff($data['trainer_ids'], $assignedIds);

        foreach ($newIds as $id) {
            $formation->trainers()->attach($id, [
                'is_lead'     => false,
                'assigned_at' => now(),
            ]);
        }

        $count   = count($newIds);
        $message = $count > 0
            ? "{$count} formateur(s) assigné(s) avec succès."
            : 'Ces formateurs sont déjà assignés à cette formation.';

        return back()->with('success', $message);
    }

    public function destroy(Formation $formation, Trainer $trainer): RedirectResponse
    {
        $this->authorize('update', $formation);

        $formation->trainers()->detach($trainer->id);

        return back()->with('success', 'Formateur retiré de la formation.');
    }
}
