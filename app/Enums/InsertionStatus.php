<?php

namespace App\Enums;

enum InsertionStatus: string
{
    case Searching = 'searching';      // En recherche
    case Internship = 'internship';    // En stage
    case Employed = 'employed';        // En emploi
    case Unemployed = 'unemployed';    // Sans emploi

    public function label(): string
    {
        return match($this) {
            self::Searching  => 'En recherche',
            self::Internship => 'En stage',
            self::Employed   => 'En emploi',
            self::Unemployed => 'Sans emploi',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Searching  => 'orange',
            self::Internship => 'blue',
            self::Employed   => 'green',
            self::Unemployed => 'gray',
        };
    }

    public function isStage(): bool
    {
        return $this === self::Internship;
    }

    public function isEmployment(): bool
    {
        return $this === self::Employed;
    }
}
