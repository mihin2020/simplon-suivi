<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // 'update' is decided in update() so a Super Admin account can never be rewritten through the user form.
        if ($user->isSuperAdmin() && $ability !== 'update') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        // The user form can only produce admin/trainer accounts: it never rewrites a Super Admin, and nobody
        // edits their own role or permissions (their own profile has its dedicated page).
        if ($target->isSuperAdmin() || $user->id === $target->id) {
            return false;
        }

        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        if ($target->isSuperAdmin()) {
            return false;
        }

        return $user->hasPermission('users.delete');
    }
}
