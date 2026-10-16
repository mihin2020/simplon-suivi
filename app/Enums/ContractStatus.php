<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'brouillon';
    case Finalized = 'finalise';
    case Signed = 'signe';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Finalized => 'Finalisé',
            self::Signed => 'Signé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Finalized => 'blue',
            self::Signed => 'emerald',
        };
    }

    /**
     * A draft is the only state whose content can still be edited freely.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
