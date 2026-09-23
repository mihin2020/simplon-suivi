<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
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
        return $user->hasPermission('forms.view');
    }

    public function view(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('forms.create');
    }

    public function update(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.update');
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.delete');
    }

    public function publish(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.publish');
    }

    public function viewResponses(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.responses');
    }

    public function select(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.select');
    }

    public function export(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.export');
    }

    public function viewStats(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.stats');
    }
}
