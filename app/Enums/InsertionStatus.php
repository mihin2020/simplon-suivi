<?php

namespace App\Enums;

enum InsertionStatus: string
{
    case Searching  = 'searching';
    case Employed   = 'employed';
    case SelfEmployed = 'self_employed';
    case Pursuing   = 'pursuing';
    case Unknown    = 'unknown';

    public function label(): string
    {
        return match($this) {
            self::Searching     => 'En recherche',
            self::Employed      => 'Employé',
            self::SelfEmployed  => 'Auto-entrepreneur',
            self::Pursuing      => 'Poursuite d\'études',
            self::Unknown       => 'Non renseigné',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Searching     => 'orange',
            self::Employed      => 'green',
            self::SelfEmployed  => 'blue',
            self::Pursuing      => 'purple',
            self::Unknown       => 'gray',
        };
    }
}
