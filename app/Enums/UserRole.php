<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Trainer = 'trainer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrateur',
            self::Admin => 'Administrateur',
            self::Trainer => 'Formateur',
        };
    }

    /**
     * Roles allowed as planning assignees (phases, activities, tasks).
     * Extend this list if business rules later open assignment to other roles.
     *
     * @return list<self>
     */
    public static function planningAssignableRoles(): array
    {
        return [self::SuperAdmin, self::Admin];
    }

    public function canBeAssignedToPlanning(): bool
    {
        return in_array($this, self::planningAssignableRoles(), true);
    }
}
