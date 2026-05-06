<?php

namespace App\Policies;

use App\Models\Learner;
use App\Models\User;

class LearnerPolicy
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
        return $user->hasPermission('learners.view');
    }

    public function view(User $user, Learner $learner): bool
    {
        return $user->hasPermission('learners.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('learners.create');
    }

    public function update(User $user, Learner $learner): bool
    {
        return $user->hasPermission('learners.update');
    }

    public function delete(User $user, Learner $learner): bool
    {
        return $user->hasPermission('learners.delete');
    }

    public function restore(User $user, Learner $learner): bool
    {
        return $user->hasPermission('learners.delete');
    }

    /** Moving a learner between formations of the same project. */
    public function move(User $user, Learner $learner): bool
    {
        return $user->hasPermission('learners.move');
    }
}
