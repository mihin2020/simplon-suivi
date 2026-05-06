<?php

namespace App\Enums;

enum LearnerStatus: string
{
    case InProgress = 'in_progress';
    case Withdrawn  = 'withdrawn';
    case Completed  = 'completed';
    case Moved      = 'moved';

    public function label(): string
    {
        return match($this) {
            self::InProgress => 'En cours',
            self::Withdrawn  => 'Abandonné',
            self::Completed  => 'Diplômé',
            self::Moved      => 'Transféré',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::InProgress => 'green',
            self::Withdrawn  => 'red',
            self::Completed  => 'blue',
            self::Moved      => 'orange',
        };
    }
}
