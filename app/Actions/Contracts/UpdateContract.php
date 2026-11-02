<?php

namespace App\Actions\Contracts;

use App\Enums\FillFieldsStatus;
use App\Enums\LetterheadScope;
use App\Jobs\ExtractContractFields;
use App\Models\Contract;
use App\Models\Letterhead;
use App\Services\Contracts\ContractPdfService;
use App\Services\Contracts\ContractTemplateImporter;
use App\Services\Contracts\LetterheadService;
use Illuminate\Support\Arr;

class UpdateContract
{
    public function __construct(
        private readonly ContractTemplateImporter $importer,
        private readonly LetterheadService $letterheads,
        private readonly ContractPdfService $pdf,
    ) {}

    /**
     * Updates a contract. A change of the text asks for the fields to be extracted again, a change of letterhead
     * re-freezes it, and an already archived PDF is regenerated so it never disagrees with the text.
     *
     * @param  array<string, mixed>  $data  Validated fields of UpdateContractRequest
     */
    public function execute(Contract $contract, array $data): void
    {
        if (isset($data['body_html'])) {
            $data['body_html'] = $this->importer->tidy($this->importer->sanitize($data['body_html']));
        }

        $textChanged = isset($data['body_html']) && $data['body_html'] !== $contract->body_html;

        $fields = Arr::except($data, ['letterhead_id', 'letterhead_scope']);

        if ($this->letterheadChanged($contract, $data)) {
            $fields += $this->letterheads->contractFields(
                isset($data['letterhead_id']) ? Letterhead::find($data['letterhead_id']) : null,
                LetterheadScope::from($data['letterhead_scope'] ?? LetterheadScope::AllPages->value),
            );
        }

        $contract->update($fields);

        if ($textChanged) {
            $contract->update(['fill_fields_status' => FillFieldsStatus::Pending]);
            ExtractContractFields::dispatch($contract);
        }

        if ($contract->pdf_path) {
            $contract->update(['pdf_path' => $this->pdf->store($contract)]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function letterheadChanged(Contract $contract, array $data): bool
    {
        if (! array_key_exists('letterhead_id', $data)) {
            return false;
        }

        $id = $data['letterhead_id'] ?? null;

        // A contract without letterhead (or one older than the library) stays as it is.
        if ($id === null && $contract->letterhead_id === null) {
            return false;
        }

        return $id !== $contract->letterhead_id
            || ($data['letterhead_scope'] ?? LetterheadScope::AllPages->value) !== $contract->letterhead_scope?->value;
    }
}
