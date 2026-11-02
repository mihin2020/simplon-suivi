<?php

namespace App\Services\Insertion;

use App\Enums\ContractTypeContext;
use App\Models\InsertionRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Stores the internship and employment contract scans attached to an entry of the insertion history. */
class InsertionContractFiles
{
    private const DISK = 'local';

    /**
     * Replaces the contract of the given kind. The record is only modified in memory: the caller saves it.
     */
    public function replace(InsertionRecord $record, ContractTypeContext $kind, UploadedFile $file): void
    {
        $this->remove($record, $kind);

        $record->{$kind->value.'_contract_path'} = $file->store('insertion-records/'.$kind->value, self::DISK);
        $record->{$kind->value.'_contract_original_name'} = $file->getClientOriginalName();
    }

    /**
     * Deletes the stored contract of the given kind. The record is only modified in memory: the caller saves it.
     */
    public function remove(InsertionRecord $record, ContractTypeContext $kind): void
    {
        $pathColumn = $kind->value.'_contract_path';

        if (! $record->{$pathColumn}) {
            return;
        }

        Storage::disk(self::DISK)->delete($record->{$pathColumn});
        $record->{$pathColumn} = null;
        $record->{$kind->value.'_contract_original_name'} = null;
    }

    public function removeAll(InsertionRecord $record): void
    {
        foreach (ContractTypeContext::cases() as $kind) {
            $this->remove($record, $kind);
        }
    }
}
