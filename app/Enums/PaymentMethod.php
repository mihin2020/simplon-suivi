<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Especes    = 'especes';
    case MobileMoney = 'mobile_money';

    public function label(): string
    {
        return match($this) {
            self::Especes     => 'Espèces',
            self::MobileMoney => 'Mobile Money',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Especes     => 'payments',
            self::MobileMoney => 'phone_android',
        };
    }
}
