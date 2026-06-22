<?php

namespace App\Enums;

enum ContractTypeContext: string
{
    case Internship = 'internship';
    case Employment = 'employment';

    public function label(): string
    {
        return match ($this) {
            self::Internship => 'Stage',
            self::Employment => 'Emploi',
        };
    }
}
