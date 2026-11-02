<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BackupListing
{
    private const PER_PAGE = 10;

    public function __construct(private readonly BackupPresenter $presenter) {}

    /**
     * Newest first. A type or a status that does not exist is ignored rather than matching nothing.
     *
     * @param  array{type: string, status: string, date_from: string, date_to: string, search: string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $type = BackupType::tryFrom($filters['type']);
        $status = BackupStatus::tryFrom($filters['status']);

        return Backup::query()
            ->with(['triggeredBy:id,first_name,last_name,email'])
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = '%'.$filters['search'].'%';

                $query->where(fn ($inner) => $inner
                    ->where('disk_path', 'like', $term)
                    ->orWhereHas('triggeredBy', fn ($user) => $user
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Backup $backup) => $this->presenter->row($backup));
    }
}
