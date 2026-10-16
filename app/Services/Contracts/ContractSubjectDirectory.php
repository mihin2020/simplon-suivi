<?php

namespace App\Services\Contracts;

use App\Enums\ContractTarget;
use App\Models\Contract;
use App\Models\Partner;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The people and organisations a contract can be addressed to, per contract type.
 */
class ContractSubjectDirectory
{
    /**
     * Recipients for a contract type: the dedicated records (trainers, CME,
     * partners), then - for people contracts - the platform users who have no
     * such record yet, so anyone already on the platform can be picked.
     *
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public function options(ContractTarget $target): array
    {
        $records = array_map(fn (array $o) => $o + ['group' => $this->groupLabel($target)], $this->records($target));

        if (! $this->acceptsUsers($target)) {
            return $records;
        }

        // Users already represented by a trainer record are not listed twice.
        $linkedUserIds = $target === ContractTarget::Trainer ? Trainer::whereNotNull('user_id')->pluck('user_id')->all() : [];

        $users = User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $linkedUserIds)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->full_name, 'group' => 'Utilisateurs de la plateforme'])
            ->all();

        return [...$records, ...$users];
    }

    /**
     * Resolve recipients by id, among the records of the type and, when
     * accepted, the platform users. Unknown ids are ignored.
     *
     * @param  list<string>  $ids
     * @return Collection<int, Model>
     */
    public function findMany(ContractTarget $target, array $ids): Collection
    {
        $model = $target->subjectModel();
        $found = $model::whereIn((new $model)->getKeyName(), $ids)->get();

        if ($this->acceptsUsers($target)) {
            $found = $found->concat(User::whereIn('id', array_diff($ids, $found->modelKeys()))->get());
        }

        // Keep the order in which the ids were given.
        return $found->sortBy(fn (Model $m) => array_search($m->getKey(), $ids, true))->values();
    }

    public function find(ContractTarget $target, ?string $id): ?Model
    {
        return $id ? $this->findMany($target, [$id])->first() : null;
    }

    private function acceptsUsers(ContractTarget $target): bool
    {
        return $target !== ContractTarget::PartnerConvention;
    }

    private function groupLabel(ContractTarget $target): string
    {
        return match ($target) {
            ContractTarget::Trainer => 'Formateurs',
            ContractTarget::Cme => 'Utilisateurs de la plateforme',
            ContractTarget::PartnerConvention => 'Partenaires',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function records(ContractTarget $target): array
    {
        return match ($target) {
            ContractTarget::Trainer => Trainer::active()->with('user:id,first_name,last_name')->get()
                ->map(fn (Trainer $t) => ['value' => $t->id, 'label' => $t->full_name])
                ->sortBy('label')->values()->all(),
            // CME are platform users, listed by options().
            ContractTarget::Cme => [],
            ContractTarget::PartnerConvention => Partner::orderBy('name')->get(['id', 'name'])
                ->map(fn (Partner $p) => ['value' => $p->id, 'label' => $p->name])
                ->all(),
        };
    }

    /**
     * Where to send a contract to its recipient, when the address is known.
     */
    public function email(?Model $subject): ?string
    {
        $email = match (true) {
            $subject instanceof Trainer => $subject->user?->email,
            $subject instanceof Partner => $subject->contact_email,
            $subject instanceof User => $subject->email,
            default => null,
        };

        return filled($email) ? (string) $email : null;
    }

    /**
     * Where a contract is sent: its own address when one was entered, else
     * the recipient record's address.
     */
    public function contractEmail(Contract $contract): ?string
    {
        return filled($contract->recipient_email) ? $contract->recipient_email : $this->email($contract->subject);
    }

    /**
     * A nominative contract is written for one known person, so it is sent to
     * that person alone; a general one can go to anyone.
     */
    public function isNominative(Contract $contract): bool
    {
        return $contract->subject_id !== null || filled($contract->recipient_email);
    }

    public function name(?Model $subject): string
    {
        return (string) ($subject?->full_name ?? $subject?->name ?? '');
    }
}
