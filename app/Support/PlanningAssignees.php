<?php

namespace App\Support;

use App\Models\User;

class PlanningAssignees
{
    /**
     * @param  list<string>|null  $assigneeIds
     * @return list<string>
     */
    public function resolve(?array $assigneeIds, User $creator): array
    {
        $ids = array_values(array_unique(array_filter($assigneeIds ?? [])));

        if ($ids === []) {
            return [$creator->id];
        }

        return $ids;
    }
}
