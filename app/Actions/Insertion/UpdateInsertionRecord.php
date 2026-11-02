<?php

namespace App\Actions\Insertion;

use App\Enums\ContractTypeContext;
use App\Models\InsertionRecord;
use App\Services\Insertion\InsertionContractFiles;
use Illuminate\Http\UploadedFile;

class UpdateInsertionRecord
{
    public function __construct(private readonly InsertionContractFiles $contracts) {}

    /**
     * A removal flag is applied before an upload, so asking for both leaves the new file.
     *
     * @param  array<string, mixed>  $attributes  Status and details of the entry.
     */
    public function execute(
        InsertionRecord $record,
        array $attributes,
        ?UploadedFile $internshipContract = null,
        ?UploadedFile $employmentContract = null,
        bool $removeInternshipContract = false,
        bool $removeEmploymentContract = false,
    ): void {
        $record->fill($attributes);

        if ($removeInternshipContract) {
            $this->contracts->remove($record, ContractTypeContext::Internship);
        }

        if ($removeEmploymentContract) {
            $this->contracts->remove($record, ContractTypeContext::Employment);
        }

        if ($internshipContract) {
            $this->contracts->replace($record, ContractTypeContext::Internship, $internshipContract);
        }

        if ($employmentContract) {
            $this->contracts->replace($record, ContractTypeContext::Employment, $employmentContract);
        }

        $record->save();
    }
}
