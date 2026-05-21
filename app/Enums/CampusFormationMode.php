<?php

namespace App\Enums;

enum CampusFormationMode: string
{
    case Presentiel = 'presentiel';
    case EnLigne    = 'en_ligne';

    public function label(): string
    {
        return match($this) {
            self::Presentiel => 'Présentiel',
            self::EnLigne    => 'En ligne',
        };
    }
}
