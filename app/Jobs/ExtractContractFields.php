<?php

namespace App\Jobs;

use App\Enums\FillFieldsStatus;
use App\Models\Contract;
use App\Services\Contracts\ContractFieldExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Prepares, in the background, the Excel model of a contract right after it
 * is saved, so it is ready when someone wants to generate it for many people.
 */
class ExtractContractFields implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Contract $contract) {}

    public function handle(ContractFieldExtractor $extractor): void
    {
        $extractor->refresh($this->contract);
    }

    public function failed(?Throwable $exception): void
    {
        $this->contract->forceFill(['fill_fields_status' => FillFieldsStatus::Failed])->saveQuietly();
    }
}
