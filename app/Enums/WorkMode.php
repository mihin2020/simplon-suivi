<?php

namespace App\Enums;

enum WorkMode: string
{
    case Presentiel = 'presentiel';
    case EnLigne = 'en_ligne';

    public function label(): string
    {
        return match ($this) {
            self::Presentiel => 'Présentiel',
            self::EnLigne => 'En ligne',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Presentiel => 'location_on',
            self::EnLigne => 'wifi',
        };
    }
}
