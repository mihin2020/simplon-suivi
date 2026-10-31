<?php

namespace App\Services\Contracts;

use App\Enums\ContractTarget;
use App\Models\Partner;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Builds the map of auto-filled variables for a contract, and exposes the
 * catalog of available variable keys per target (used by the template editor).
 */
class ContractVariableResolver
{
    /**
     * Catalog of auto variables available for a given target: [key => label].
     *
     * @return array<string, string>
     */
    public function catalog(ContractTarget $target): array
    {
        return array_merge($this->subjectCatalog($target), $this->commonCatalog());
    }

    /**
     * @return array<string, string>
     */
    private function commonCatalog(): array
    {
        return [
            'today' => 'Date du jour',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function subjectCatalog(ContractTarget $target): array
    {
        return match ($target) {
            ContractTarget::Trainer => [
                'trainer.first_name' => 'Formateur : prénom',
                'trainer.last_name' => 'Formateur : nom',
                'trainer.full_name' => 'Formateur : nom complet',
                'trainer.email' => 'Formateur : email',
                'trainer.phone' => 'Formateur : téléphone',
                'trainer.address' => 'Formateur : adresse',
                'trainer.id_number' => 'Formateur : pièce d\'identité',
                'trainer.birth_date' => 'Formateur : date de naissance',
                'trainer.birth_place' => 'Formateur : lieu de naissance',
                'trainer.profile' => 'Formateur : profil',
            ],
            ContractTarget::Cme => [
                'cme.first_name' => 'CME : prénom',
                'cme.last_name' => 'CME : nom',
                'cme.full_name' => 'CME : nom complet',
                'cme.email' => 'CME : email',
            ],
            ContractTarget::PartnerConvention => [
                'partner.name' => 'Partenaire : raison sociale',
                'partner.contact_first_name' => 'Partenaire : contact prénom',
                'partner.contact_last_name' => 'Partenaire : contact nom',
                'partner.contact_full_name' => 'Partenaire : contact nom complet',
                'partner.contact_email' => 'Partenaire : contact email',
                'partner.contact_phone' => 'Partenaire : contact téléphone',
                'partner.contact_position' => 'Partenaire : contact fonction',
            ],
        };
    }

    /**
     * Resolve the actual values for the auto variables of a contract subject.
     *
     * @return array<string, string>
     */
    public function resolve(ContractTarget $target, Model $subject): array
    {
        return array_merge(
            $this->resolveSubject($target, $subject),
            $this->resolveCommon(),
        );
    }

    /**
     * What is known about a recipient, keyed by its French label and without
     * the empty values - the form handed to the AI drafting assistant.
     *
     * @return array<string, string>
     */
    public function facts(ContractTarget $target, Model $subject): array
    {
        $labels = $this->catalog($target);
        $facts = [];

        foreach ($this->resolve($target, $subject) as $key => $value) {
            if (trim($value) !== '') {
                $facts[$labels[$key] ?? $key] = $value;
            }
        }

        return $facts;
    }

    /**
     * @return array<string, string>
     */
    private function resolveCommon(): array
    {
        return [
            'today' => Carbon::now()->locale('fr')->translatedFormat('d F Y'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveSubject(ContractTarget $target, Model $subject): array
    {
        // A platform user picked directly (no trainer / CME record yet): only
        // the identity the account holds is known.
        if ($subject instanceof User) {
            $prefix = $target === ContractTarget::Cme ? 'cme' : 'trainer';

            return [
                "{$prefix}.first_name" => (string) $subject->first_name,
                "{$prefix}.last_name" => (string) $subject->last_name,
                "{$prefix}.full_name" => (string) $subject->full_name,
                "{$prefix}.email" => (string) $subject->email,
            ];
        }

        return match ($target) {
            ContractTarget::Trainer => $this->resolveTrainer($subject),
            // A CME is a platform user, resolved above.
            ContractTarget::Cme => [],
            ContractTarget::PartnerConvention => $this->resolvePartner($subject),
        };
    }

    /**
     * @return array<string, string>
     */
    private function resolveTrainer(Trainer $trainer): array
    {
        $trainer->loadMissing(['user', 'profiles']);
        $user = $trainer->user;

        return [
            'trainer.first_name' => (string) ($user?->first_name),
            'trainer.last_name' => (string) ($user?->last_name),
            'trainer.full_name' => (string) ($user?->full_name),
            'trainer.email' => (string) ($user?->email),
            'trainer.phone' => (string) $trainer->phone,
            'trainer.address' => (string) $trainer->address,
            'trainer.id_number' => (string) $trainer->id_number,
            'trainer.birth_date' => $trainer->birth_date?->locale('fr')->translatedFormat('d F Y') ?? '',
            'trainer.birth_place' => (string) $trainer->birth_place,
            'trainer.profile' => $trainer->profiles->pluck('name')->implode(', '),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolvePartner(Partner $partner): array
    {
        $fullName = trim("{$partner->contact_first_name} {$partner->contact_last_name}");

        return [
            'partner.name' => (string) $partner->name,
            'partner.contact_first_name' => (string) $partner->contact_first_name,
            'partner.contact_last_name' => (string) $partner->contact_last_name,
            'partner.contact_full_name' => $fullName,
            'partner.contact_email' => (string) $partner->contact_email,
            'partner.contact_phone' => (string) $partner->contact_phone,
            'partner.contact_position' => (string) $partner->contact_position,
        ];
    }
}
