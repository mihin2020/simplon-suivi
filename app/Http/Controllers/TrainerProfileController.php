<?php

namespace App\Http\Controllers;

use App\Models\TrainerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainerProfileController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('TrainerProfiles/Index', [
            'profiles' => TrainerProfile::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:trainer_profiles,name'],
        ]);

        TrainerProfile::create($request->only('name'));

        return back()->with('success', 'Profil créé.');
    }

    public function update(Request $request, TrainerProfile $trainerProfile): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:trainer_profiles,name,' . $trainerProfile->id],
        ]);

        $trainerProfile->update($request->only('name'));

        return back()->with('success', 'Profil mis à jour.');
    }

    public function destroy(TrainerProfile $trainerProfile): RedirectResponse
    {
        $trainerProfile->delete();

        return back()->with('success', 'Profil supprimé.');
    }
}
