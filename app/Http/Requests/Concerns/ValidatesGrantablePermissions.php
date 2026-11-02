<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

trait ValidatesGrantablePermissions
{
    /**
     * An admin can only grant permissions they hold themselves (plus those the target already has),
     * otherwise creating or editing an account would be a way around their own restrictions.
     *
     * @param  array<int, int>  $alreadyGranted  Permission ids the target account already holds
     */
    protected function ensurePermissionsAreGrantable(Validator $validator, array $alreadyGranted = []): void
    {
        $actor = $this->user();
        $requested = array_map('intval', (array) $this->input('permissions', []));

        if ($actor->isSuperAdmin() || $requested === []) {
            return;
        }

        $allowed = $actor->permissions->pluck('id')->merge($alreadyGranted)->all();

        if (array_diff($requested, $allowed) !== []) {
            $validator->errors()->add('permissions', 'Vous ne pouvez pas accorder une permission que vous ne possédez pas.');
        }
    }
}
