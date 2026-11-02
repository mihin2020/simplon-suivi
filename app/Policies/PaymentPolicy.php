<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Correcting an installment (amount or date) rewrites financial history: only a Super Admin or an Admin
     * may do it, not a collector who merely has the finance permission.
     */
    public function correct(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }
}
