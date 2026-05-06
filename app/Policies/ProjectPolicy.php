<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
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
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete');
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete');
    }
}
