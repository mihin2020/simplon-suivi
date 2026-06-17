<?php

namespace App\Http\Controllers;

use App\Actions\CreateTrainerAction;
use App\Actions\DeleteTrainerAction;
use App\Actions\InviteUser;
use App\Actions\UpdateTrainerAction;
use App\Enums\UserRole;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Project;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(['permissions', 'trainer.formations.project'])
            ->when(request('search'), function ($q, $s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            })
            ->when(request('role'), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $projects = Project::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Users/Index', [
            'users'       => $users,
            'filters'     => request()->only('search', 'role'),
            'roles'       => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
            'projects'    => $projects,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles'           => collect(UserRole::cases())
                ->reject(fn ($r) => $r === UserRole::SuperAdmin)
                ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])
                ->values(),
            'permissions'     => Permission::orderBy('group')->orderBy('name')->get(),
            'trainerProfiles' => TrainerProfile::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        StoreUserRequest $request,
        InviteUser $inviteUser,
        CreateTrainerAction $createTrainer,
    ): RedirectResponse {
        $role = UserRole::from($request->validated('role'));

        if ($role === UserRole::Trainer) {
            $createTrainer->execute(
                firstName: $request->validated('first_name'),
                lastName:  $request->validated('last_name'),
                email:     $request->validated('email'),
                profileId: $request->validated('profile_id'),
                phone:     $request->validated('phone'),
                phone2:    $request->validated('phone2'),
                cv:        $request->file('cv'),
            );
        } else {
            $user = $inviteUser->execute(
                firstName: $request->validated('first_name'),
                lastName:  $request->validated('last_name'),
                email:     $request->validated('email'),
                role:      $role,
            );

            if ($request->filled('permissions')) {
                $user->permissions()->sync($request->validated('permissions'));
            }
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'Utilisateur invité avec succès. Un email d\'activation a été envoyé.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['permissions', 'trainer']);

        return Inertia::render('Users/Edit', [
            'user'            => $user,
            'roles'           => collect(UserRole::cases())
                ->reject(fn ($r) => $r === UserRole::SuperAdmin)
                ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])
                ->values(),
            'permissions'     => Permission::orderBy('group')->orderBy('name')->get(),
            'userPermissions' => $user->permissions->pluck('id'),
            'trainerProfiles' => TrainerProfile::orderBy('name')->get(['id', 'name']),
            'trainerData'     => $user->trainer,
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateTrainerAction $updateTrainer,
    ): RedirectResponse {
        $role = UserRole::from($request->validated('role'));

        $user->update([
            'first_name' => $request->validated('first_name'),
            'last_name'  => $request->validated('last_name'),
            'email'      => $request->validated('email'),
            'role'       => $role,
            'is_active'  => $request->validated('is_active', $user->is_active),
        ]);

        if ($role === UserRole::Trainer) {
            $updateTrainer->execute(
                user:      $user,
                profileId: $request->validated('profile_id'),
                phone:     $request->validated('phone'),
                phone2:    $request->validated('phone2'),
                cv:        $request->file('cv'),
                removeCv:  (bool) $request->validated('remove_cv', false),
            );
        } else {
            $user->permissions()->sync($request->validated('permissions', []));
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'Utilisateur mis à jour avec succès.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->isSuperAdmin()) {
            abort(403, 'Impossible de désactiver le Super Administrateur.');
        }

        $user->update(['is_active' => !$user->is_active]);

        $state = $user->is_active ? 'activé' : 'désactivé';

        return back()->with('success', "Utilisateur {$state} avec succès.");
    }

    public function destroy(User $user, DeleteTrainerAction $deleteTrainer): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->isSuperAdmin()) {
            abort(403, 'Impossible de supprimer le Super Administrateur.');
        }

        if ($user->trainer) {
            $deleteTrainer->execute($user->trainer);
        } else {
            $user->delete();
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'Utilisateur supprimé.');
    }
}
