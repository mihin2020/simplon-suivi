<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EducationLevelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('education_levels', 'name')],
        ]);

        EducationLevel::create($validated);

        return redirect()->route('configuration')->with('success', 'Niveau d\'études ajouté.');
    }

    public function update(Request $request, EducationLevel $educationLevel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('education_levels', 'name')->ignore($educationLevel->id)],
        ]);

        $educationLevel->update($validated);

        return redirect()->route('configuration')->with('success', 'Niveau d\'études mis à jour.');
    }

    public function destroy(EducationLevel $educationLevel): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        // Check if there are learners using this education level
        if ($educationLevel->learners()->count() > 0) {
            $message = 'Impossible de supprimer ce niveau d\'études car il est utilisé par des apprenants.';

            // Return JSON for Inertia AJAX requests
            if (request()->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('configuration')->with('error', $message);
        }

        $educationLevel->delete();

        return redirect()->route('configuration')->with('success', 'Niveau d\'études supprimé.');
    }
}
