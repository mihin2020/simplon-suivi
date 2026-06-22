<?php

namespace App\Enums;

enum PartnerCategory: string
{
    case Bailleur = 'bailleur';
    case Client = 'client';
    case Mixte = 'mixte';

    public function label(): string
    {
        return match ($this) {
            self::Bailleur => 'Bailleur',
            self::Client => 'Client',
            self::Mixte => 'Mixte',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Bailleur => 'navy',
            self::Client => 'primary',
            self::Mixte => 'purple',
        };
    }
}
