<?php

namespace App\Policies;

use App\Models\Letterhead;
use App\Models\User;

class LetterheadPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contracts.templates');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contracts.templates');
    }

    public function update(User $user, Letterhead $letterhead): bool
    {
        return $user->hasPermission('contracts.templates');
    }

    public function delete(User $user, Letterhead $letterhead): bool
    {
        return $user->hasPermission('contracts.templates');
    }
}
