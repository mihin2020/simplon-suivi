<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
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
        return $user->hasPermission('partners.view');
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('partners.create');
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.update');
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->hasPermission('partners.delete');
    }
}
