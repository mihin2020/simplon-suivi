<?php

namespace App\Actions\Insertion;

use App\Models\InsertionRecord;
use App\Services\Insertion\InsertionContractFiles;

class DeleteInsertionRecord
{
    public function __construct(private readonly InsertionContractFiles $contracts) {}

    public function execute(InsertionRecord $record): void
    {
        $this->contracts->removeAll($record);
        $record->delete();
    }
}
