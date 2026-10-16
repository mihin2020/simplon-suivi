<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
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
        return $user->hasPermission('contracts.view');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contracts.create');
    }

    /**
     * Permission only. The "a finalized contract is locked" rule is a business
     * invariant enforced in the controller, because before() would let a Super
     * Admin bypass it and silently rewrite a signed document.
     */
    public function update(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.update');
    }

    public function finalize(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.update');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.delete');
    }
}
