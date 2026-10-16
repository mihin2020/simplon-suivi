<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use Illuminate\Support\Str;

class ContractReferenceGenerator
{
    /**
     * A new unique reference such as CTR-202610-K3F9Q. Soft-deleted contracts
     * keep theirs, so they are taken into account too.
     */
    public function next(): string
    {
        do {
            $reference = 'CTR-'.now()->format('Ym').'-'.strtoupper(Str::random(5));
        } while (Contract::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }
}
