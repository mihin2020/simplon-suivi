<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case EnAttente = 'en_attente';
    case Paye      = 'paye';
    case EnRetard  = 'en_retard';
    case Annule    = 'annule';

    public function label(): string
    {
        return match($this) {
            self::EnAttente => 'En attente',
            self::Paye      => 'Payé',
            self::EnRetard  => 'En retard',
            self::Annule    => 'Annulé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::EnAttente => 'amber',
            self::Paye      => 'emerald',
            self::EnRetard  => 'rose',
            self::Annule    => 'gray',
        };
    }
}
