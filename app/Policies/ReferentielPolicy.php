<?php

namespace App\Policies;

use App\Models\Referentiel;
use App\Models\User;

class ReferentielPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('referentiels.view');
    }

    public function view(User $user, Referentiel $referentiel): bool
    {
        return $user->hasPermission('referentiels.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('referentiels.create');
    }

    public function update(User $user, Referentiel $referentiel): bool
    {
        return $user->hasPermission('referentiels.update');
    }

    public function delete(User $user, Referentiel $referentiel): bool
    {
        return $user->hasPermission('referentiels.delete');
    }
}
