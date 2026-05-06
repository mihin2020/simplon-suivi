<?php

namespace App\Enums;

enum AttendanceCode: string
{
    case Present          = 'P';
    case AbsentJustified  = 'AJ';
    case AbsentNotJustified = 'AN';
    case LateJustified    = 'RJ';
    case LateNotJustified = 'RN';

    public function label(): string
    {
        return match($this) {
            self::Present             => 'Présent',
            self::AbsentJustified     => 'Absent justifié',
            self::AbsentNotJustified  => 'Absent non justifié',
            self::LateJustified       => 'Retard justifié',
            self::LateNotJustified    => 'Retard non justifié',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Present             => 'green',
            self::AbsentJustified     => 'orange',
            self::AbsentNotJustified  => 'red',
            self::LateJustified       => 'yellow',
            self::LateNotJustified    => 'red',
        };
    }

    public function isAbsent(): bool
    {
        return in_array($this, [self::AbsentJustified, self::AbsentNotJustified]);
    }

    public function countsAsPresent(): bool
    {
        return $this === self::Present || $this === self::LateJustified || $this === self::LateNotJustified;
    }
}
