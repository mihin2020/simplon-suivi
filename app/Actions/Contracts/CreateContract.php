<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Enums\LetterheadScope;
use App\Jobs\ExtractContractFields;
use App\Models\Contract;
use App\Models\Letterhead;
use App\Models\User;
use App\Services\Contracts\ContractReferenceGenerator;
use App\Services\Contracts\ContractSubjectDirectory;
use App\Services\Contracts\ContractTemplateImporter;
use App\Services\Contracts\LetterheadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateContract
{
    public function __construct(
        private readonly ContractTemplateImporter $importer,
        private readonly ContractSubjectDirectory $subjects,
        private readonly ContractReferenceGenerator $references,
        private readonly LetterheadService $letterheads,
    ) {}

    /**
     * Creates a draft contract from the authored text.
     *
     * @param  array<string, mixed>  $data  Validated fields of StoreContractRequest
     *
     * @throws ValidationException when the recipient does not match the type of contract
     */
    public function execute(array $data, User $creator): Contract
    {
        $target = ContractTarget::from($data['target']);

        // The recipient is optional: a contract may be drafted before the person or partner exists.
        $subject = $this->subjects->find($target, $data['subject_id'] ?? null);

        if (isset($data['subject_id']) && $subject === null) {
            throw ValidationException::withMessages(['subject_id' => 'Ce destinataire ne correspond pas au type de contrat.']);
        }

        $letterhead = isset($data['letterhead_id']) ? Letterhead::find($data['letterhead_id']) : null;
        $scope = LetterheadScope::from($data['letterhead_scope'] ?? LetterheadScope::AllPages->value);

        $contract = Contract::create([
            'reference' => $this->references->next(),
            'contract_project_id' => $data['contract_project_id'] ?? null,
            'target' => $target,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'title' => $data['name'],
            // The text is final (no variables): only cleaned before being stored.
            'body_html' => $this->importer->tidy($this->importer->sanitize($data['body_html'])),
            'signatories' => $data['signatories'] ?? null,
            ...($letterhead ? $this->letterheads->contractFields($letterhead, $scope) : []),
            'font' => $data['font'] ?? null,
            'line_spacing' => $data['line_spacing'] ?? null,
            'data' => [],
            'fill_fields_status' => FillFieldsStatus::Pending,
            'status' => ContractStatus::Draft,
            'version' => 1,
            'created_by' => $creator->id,
        ]);

        // Ready before anyone asks to generate this contract for other people.
        ExtractContractFields::dispatch($contract);

        Log::info('Contract generated', ['reference' => $contract->reference, 'user_id' => $creator->id]);

        return $contract;
    }
}
