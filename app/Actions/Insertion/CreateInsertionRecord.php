<?php

namespace App\Actions\Insertion;

use App\Enums\ContractTypeContext;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Services\Insertion\InsertionContractFiles;
use Illuminate\Http\UploadedFile;

class CreateInsertionRecord
{
    public function __construct(private readonly InsertionContractFiles $contracts) {}

    /**
     * @param  array<string, mixed>  $attributes  Status and details of the entry.
     */
    public function execute(
        Learner $learner,
        array $attributes,
        mixed $statusChangedAt,
        string $recordedBy,
        ?UploadedFile $internshipContract = null,
        ?UploadedFile $employmentContract = null,
    ): InsertionRecord {
        $record = new InsertionRecord([
            ...$attributes,
            'learner_id' => $learner->id,
            'status_changed_at' => $statusChangedAt ?? now(),
            'recorded_by' => $recordedBy,
        ]);

        if ($internshipContract) {
            $this->contracts->replace($record, ContractTypeContext::Internship, $internshipContract);
        }

        if ($employmentContract) {
            $this->contracts->replace($record, ContractTypeContext::Employment, $employmentContract);
        }

        $record->save();

        return $record;
    }
}
