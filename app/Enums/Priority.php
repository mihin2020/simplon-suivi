<?php

namespace App\Enums;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Basse',
            self::Medium => 'Moyenne',
            self::High => 'Haute',
            self::Urgent => 'Urgente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => '#6b7280',
            self::Medium => '#2563eb',
            self::High => '#d97706',
            self::Urgent => '#dc2626',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'keyboard_arrow_down',
            self::Medium => 'remove',
            self::High => 'keyboard_arrow_up',
            self::Urgent => 'priority_high',
        };
    }
}
