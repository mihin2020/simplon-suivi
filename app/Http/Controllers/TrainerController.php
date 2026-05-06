<?php

namespace App\Http\Controllers;

use App\Actions\InviteUser;
use App\Enums\UserRole;
use App\Http\Requests\Trainer\StoreTrainerRequest;
use App\Http\Requests\Trainer\UpdateTrainerRequest;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TrainerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Trainer::class);

        $trainers = Trainer::with('user')
            ->when(request('search'), function ($q, $s) {
                $q->whereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%"));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Trainers/Index', [
            'trainers' => $trainers,
            'filters'  => request()->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Trainer::class);

        return Inertia::render('Trainers/Create');
    }

    public function store(StoreTrainerRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        $user = $inviteUser->execute(
            firstName: $request->validated('first_name'),
            lastName:  $request->validated('last_name'),
            email:     $request->validated('email'),
            role:      UserRole::Trainer,
        );

        $trainer = Trainer::create([
            'user_id'   => $user->id,
            'specialty' => $request->validated('specialty'),
            'phone'     => $request->validated('phone'),
        ]);

        return redirect()
            ->route('trainers.show', $trainer)
            ->with('success', 'Formateur invité avec succès. Un email d\'activation a été envoyé.');
    }

    public function show(Trainer $trainer): Response
    {
        $this->authorize('view', $trainer);

        $trainer->load(['user', 'formations.project']);

        return Inertia::render('Trainers/Show', [
            'trainer' => $trainer,
        ]);
    }

    public function edit(Trainer $trainer): Response
    {
        $this->authorize('update', $trainer);

        return Inertia::render('Trainers/Edit', [
            'trainer' => $trainer->load('user'),
        ]);
    }

    public function update(UpdateTrainerRequest $request, Trainer $trainer): RedirectResponse
    {
        $trainer->update($request->validated());

        return redirect()
            ->route('trainers.show', $trainer)
            ->with('success', 'Formateur mis à jour avec succès.');
    }

    public function destroy(Trainer $trainer): RedirectResponse
    {
        $this->authorize('delete', $trainer);

        $trainer->delete();

        return redirect()
            ->route('trainers.index')
            ->with('success', 'Formateur supprimé.');
    }
}
