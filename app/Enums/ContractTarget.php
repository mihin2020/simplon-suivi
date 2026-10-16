<?php

namespace App\Enums;

use App\Models\Partner;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

enum ContractTarget: string
{
    case Trainer = 'trainer';
    case Cme = 'cme';
    case PartnerConvention = 'partner_convention';

    public function label(): string
    {
        return match ($this) {
            self::Trainer => 'Contrat formateur',
            self::Cme => 'Contrat chargé médiation emploi',
            self::PartnerConvention => 'Convention de partenariat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Trainer => 'blue',
            self::Cme => 'violet',
            self::PartnerConvention => 'emerald',
        };
    }

    /**
     * Eloquent model class acting as the contract subject for this target.
     *
     * @return class-string<Model>
     */
    public function subjectModel(): string
    {
        return match ($this) {
            self::Trainer => Trainer::class,
            // No dedicated record: a CME is picked among the platform users.
            self::Cme => User::class,
            self::PartnerConvention => Partner::class,
        };
    }
}
