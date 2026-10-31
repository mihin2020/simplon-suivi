<?php

namespace App\Enums;

enum DayType: string
{
    case Course = 'course';
    case Weekend = 'weekend';
    case OutsidePeriod = 'outside_period';

    public function label(): ?string
    {
        return match ($this) {
            self::Course => null,
            self::Weekend => 'Week-end : pas de formation',
            self::OutsidePeriod => 'Hors de la période de la formation',
        };
    }
}
