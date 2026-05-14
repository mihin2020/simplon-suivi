<?php

namespace App\Http\Controllers;

use App\Models\AgeRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgeRangeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'age_min' => ['required', 'integer', 'min:0', 'max:150'],
            'age_max' => ['required', 'integer', 'min:0', 'max:150', 'gte:age_min'],
        ]);

        $validated['name'] = $this->buildName($validated['age_min'], $validated['age_max']);

        AgeRange::create($validated);

        return redirect()->route('configuration')->with('success', 'Tranche d\'âge ajoutée.');
    }

    public function update(Request $request, AgeRange $ageRange): RedirectResponse
    {
        $validated = $request->validate([
            'age_min' => ['required', 'integer', 'min:0', 'max:150'],
            'age_max' => ['required', 'integer', 'min:0', 'max:150', 'gte:age_min'],
        ]);

        $validated['name'] = $this->buildName($validated['age_min'], $validated['age_max']);

        $ageRange->update($validated);

        return redirect()->route('configuration')->with('success', 'Tranche d\'âge mise à jour.');
    }

    private function buildName(int $min, int $max): string
    {
        if ($max >= 150) {
            return "{$min} ans et +";
        }
        return "{$min} - {$max} ans";
    }

    public function destroy(AgeRange $ageRange): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if ($ageRange->learners()->count() > 0) {
            $message = 'Impossible de supprimer cette tranche d\'âge car elle est utilisée par des apprenants.';

            if (request()->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('configuration')->with('error', $message);
        }

        $ageRange->delete();

        return redirect()->route('configuration')->with('success', 'Tranche d\'âge supprimée.');
    }
}
