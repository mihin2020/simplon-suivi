<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'En cours',
            self::Completed => 'Terminé',
            self::Archived  => 'Archivé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active    => 'green',
            self::Completed => 'blue',
            self::Archived  => 'gray',
        };
    }
}
