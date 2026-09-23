<?php

namespace App\Enums;

enum BackupType: string
{
    case Manual = 'manual';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuel',
            self::Auto => 'Automatique',
        };
    }
}
