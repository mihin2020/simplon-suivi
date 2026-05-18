<?php

namespace App\Http\Controllers;

use App\Actions\InviteUser;
use App\Enums\UserRole;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Trainer;
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

        $users = User::with('permissions')
            ->when(request('search'), function ($q, $s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            })
            ->when(request('role'), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users'       => $users,
            'filters'     => request()->only('search', 'role'),
            'roles'       => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
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

    public function store(StoreUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        $role = UserRole::from($request->validated('role'));

        $user = $inviteUser->execute(
            firstName: $request->validated('first_name'),
            lastName:  $request->validated('last_name'),
            email:     $request->validated('email'),
            role:      $role,
        );

        if ($role === UserRole::Admin && $request->filled('permissions')) {
            $user->permissions()->sync($request->validated('permissions'));
        }

        if ($role === UserRole::Trainer) {
            $cvPath = null;
            if ($request->hasFile('cv')) {
                $cvPath = $request->file('cv')->store('trainers/cvs', 'public');
            }

            Trainer::create([
                'user_id'    => $user->id,
                'profile_id' => $request->validated('profile_id'),
                'phone'      => $request->validated('phone'),
                'phone2'     => $request->validated('phone2'),
                'cv_path'    => $cvPath,
            ]);
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
            'trainerData'     => $user->trainer ? [
                'profile_id' => $user->trainer->profile_id,
                'phone'      => $user->trainer->phone,
                'phone2'     => $user->trainer->phone2,
                'cv_path'    => $user->trainer->cv_path,
            ] : null,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $newRole = UserRole::from($request->validated('role'));

        $user->update([
            'first_name' => $request->validated('first_name'),
            'last_name'  => $request->validated('last_name'),
            'email'      => $request->validated('email'),
            'role'       => $newRole,
            'is_active'  => $request->validated('is_active', $user->is_active),
        ]);

        if ($newRole === UserRole::Admin) {
            $user->permissions()->sync($request->validated('permissions', []));
        } else {
            $user->permissions()->detach();
        }

        if ($newRole === UserRole::Trainer) {
            $cvPath = $user->trainer?->cv_path;
            if ($request->hasFile('cv')) {
                $cvPath = $request->file('cv')->store('trainers/cvs', 'public');
            }

            Trainer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profile_id' => $request->validated('profile_id'),
                    'phone'      => $request->validated('phone'),
                    'phone2'     => $request->validated('phone2'),
                    'cv_path'    => $cvPath,
                ]
            );
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

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->isSuperAdmin()) {
            abort(403, 'Impossible de supprimer le Super Administrateur.');
        }

        $user->trainer?->delete();
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Utilisateur supprimé.');
    }
}
