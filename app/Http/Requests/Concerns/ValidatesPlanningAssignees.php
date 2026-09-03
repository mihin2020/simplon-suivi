<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

trait ValidatesPlanningAssignees
{
    /**
     * @return array<string, mixed>
     */
    protected function assigneeRules(): array
    {
        return [
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => [
                'uuid',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('is_active', true)
                        ->whereIn('role', array_map(
                            fn (UserRole $role) => $role->value,
                            UserRole::planningAssignableRoles()
                        ))
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }
}
