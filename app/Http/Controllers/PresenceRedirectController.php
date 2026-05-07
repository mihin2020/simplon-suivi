<?php

namespace App\Http\Controllers;

use App\Enums\FormationStatus;
use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PresenceRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->role !== UserRole::Trainer) {
            return redirect()->route('projects.index');
        }

        $trainer = $user->trainer;

        if (! $trainer) {
            abort(403, 'Aucun profil formateur lié à ce compte.');
        }

        $formation = $trainer->formations()
            ->where('status', FormationStatus::Active)
            ->first();

        if (! $formation) {
            $formation = $trainer->formations()->first();
        }

        if (! $formation) {
            abort(403, 'Aucune formation assignée.');
        }

        return redirect()->route('attendances.index', $formation);
    }
}
