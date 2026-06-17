<?php

namespace App\Http\Controllers;

use App\Mail\UserInvitation;
use App\Models\ActivationToken;
use App\Models\Formation;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TrainerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Trainer::class);

        $trainers = Trainer::with(['user', 'profile', 'formations.project'])
            ->whereHas('user')
            ->when(request('search'), function ($q, $s) {
                $q->whereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%"));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Projets pour le sélecteur d'assignation
        $projects = Project::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Trainers/Index', [
            'trainers' => $trainers,
            'filters'  => request()->only('search'),
            'projects' => $projects,
        ]);
    }

    public function show(Trainer $trainer): Response
    {
        $this->authorize('view', $trainer);

        $trainer->load(['user', 'profile', 'formations.project']);

        return Inertia::render('Trainers/Show', [
            'trainer' => $trainer,
        ]);
    }

    /**
     * Renvoyer l'invitation d'activation au formateur
     */
    public function resendInvitation(Trainer $trainer): RedirectResponse
    {
        $this->authorize('update', $trainer);

        $user = $trainer->user;

        if ($user->is_active) {
            return back()->with('error', 'Ce formateur a déjà activé son compte.');
        }

        // Supprimer les anciens tokens d'activation
        ActivationToken::where('user_id', $user->id)
            ->where('type', 'activation')
            ->delete();

        // Créer un nouveau token
        $plainToken = Str::random(64);
        ActivationToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plainToken),
            'type'       => 'activation',
            'expires_at' => now()->addHours(72),
        ]);

        // Renvoyer l'email
        Mail::to($user->email)->queue(
            (new UserInvitation($user, $plainToken))->onConnection('database')
        );

        return back()->with('success', 'L\'invitation a été renvoyée avec succès.');
    }

    /**
     * Assigner un formateur à une ou plusieurs formations
     */
    public function assignFormation(Request $request, Trainer $trainer): RedirectResponse
    {
        $this->authorize('update', $trainer);

        $data = $request->validate([
            'formation_ids'   => ['required', 'array', 'min:1'],
            'formation_ids.*' => ['uuid', 'exists:formations,id'],
        ]);

        $assignedCount = 0;

        foreach ($data['formation_ids'] as $formationId) {
            $formation = Formation::findOrFail($formationId);
            $this->authorize('update', $formation);

            $alreadyAssigned = $formation->trainers()->where('trainers.id', $trainer->id)->exists();

            if (!$alreadyAssigned) {
                $formation->trainers()->attach($trainer->id, [
                    'is_lead'     => false,
                    'assigned_at' => now(),
                ]);
                $assignedCount++;
            }
        }

        $message = $assignedCount > 0
            ? "Formateur assigné à {$assignedCount} formation(s) avec succès."
            : 'Le formateur est déjà assigné à toutes les formations sélectionnées.';

        return back()->with('success', $message);
    }

    /**
     * Désassigner un formateur d'une formation
     */
    public function unassignFormation(Trainer $trainer, Formation $formation): RedirectResponse
    {
        $this->authorize('update', $trainer);
        $this->authorize('update', $formation);

        $formation->trainers()->detach($trainer->id);

        return back()->with('success', 'Formateur retiré de la formation.');
    }
}
