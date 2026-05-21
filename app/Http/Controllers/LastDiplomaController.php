<?php

namespace App\Http\Controllers;

use App\Models\LastDiploma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LastDiplomaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('last_diplomas', 'name')],
        ]);

        LastDiploma::create($validated);

        return redirect()->route('configuration')->with('success', 'Diplôme ajouté.');
    }

    public function update(Request $request, LastDiploma $lastDiploma): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('last_diplomas', 'name')->ignore($lastDiploma->id)],
        ]);

        $lastDiploma->update($validated);

        return redirect()->route('configuration')->with('success', 'Diplôme mis à jour.');
    }

    public function destroy(LastDiploma $lastDiploma): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if ($lastDiploma->learners()->count() > 0) {
            $message = 'Impossible de supprimer ce diplôme car il est utilisé par des apprenants.';

            if (request()->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('configuration')->with('error', $message);
        }

        $lastDiploma->delete();

        return redirect()->route('configuration')->with('success', 'Diplôme supprimé.');
    }
}
