<?php

namespace App\Policies;

use App\Models\Trainer;
use App\Models\User;

class TrainerPolicy
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
        return $user->hasPermission('trainers.view');
    }

    public function view(User $user, Trainer $trainer): bool
    {
        if ($user->trainer?->id === $trainer->id) {
            return true;
        }

        return $user->hasPermission('trainers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('trainers.create');
    }

    public function update(User $user, Trainer $trainer): bool
    {
        return $user->hasPermission('trainers.update');
    }

    public function delete(User $user, Trainer $trainer): bool
    {
        return $user->hasPermission('trainers.delete');
    }

    public function restore(User $user, Trainer $trainer): bool
    {
        return $user->hasPermission('trainers.delete');
    }
}
