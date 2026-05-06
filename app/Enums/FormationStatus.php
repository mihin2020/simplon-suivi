<?php

namespace App\Enums;

enum FormationStatus: string
{
    case Active    = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived  = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'En cours',
            self::Completed => 'Terminée',
            self::Cancelled => 'Annulée',
            self::Archived  => 'Archivée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active    => 'green',
            self::Completed => 'blue',
            self::Cancelled => 'red',
            self::Archived  => 'gray',
        };
    }
}
