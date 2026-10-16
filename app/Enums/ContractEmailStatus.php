<?php

namespace App\Enums;

enum ContractEmailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Envoi en cours',
            self::Sent => 'Envoyé',
            self::Failed => 'Échec de l\'envoi',
        };
    }
}
