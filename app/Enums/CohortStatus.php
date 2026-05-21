<?php

namespace App\Enums;

enum CohortStatus: string
{
    case Planifiee = 'planifiee';
    case EnCours   = 'en_cours';
    case Cloturee  = 'cloturee';

    public function label(): string
    {
        return match($this) {
            self::Planifiee => 'Planifiée',
            self::EnCours   => 'En cours',
            self::Cloturee  => 'Clôturée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Planifiee => 'blue',
            self::EnCours   => 'green',
            self::Cloturee  => 'gray',
        };
    }
}
