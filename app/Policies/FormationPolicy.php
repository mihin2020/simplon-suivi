<?php

namespace App\Policies;

use App\Models\Formation;
use App\Models\User;

class FormationPolicy
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
        return $user->hasPermission('formations.view');
    }

    public function view(User $user, Formation $formation): bool
    {
        return $user->hasPermission('formations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('formations.create');
    }

    public function update(User $user, Formation $formation): bool
    {
        return $user->hasPermission('formations.update');
    }

    public function delete(User $user, Formation $formation): bool
    {
        return $user->hasPermission('formations.delete');
    }

    public function restore(User $user, Formation $formation): bool
    {
        return $user->hasPermission('formations.delete');
    }
}
