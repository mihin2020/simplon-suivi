<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Trainer = 'trainer';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin => 'Super Administrateur',
            self::Admin => 'Administrateur',
            self::Trainer => 'Formateur',
        };
    }
}
