<?php

namespace App\Policies;

use App\Models\ContractProject;
use App\Models\User;

class ContractProjectPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contracts.view');
    }

    public function view(User $user, ContractProject $project): bool
    {
        return $user->hasPermission('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contracts.create');
    }

    public function update(User $user, ContractProject $project): bool
    {
        return $user->hasPermission('contracts.update');
    }

    public function delete(User $user, ContractProject $project): bool
    {
        return $user->hasPermission('contracts.delete');
    }
}
