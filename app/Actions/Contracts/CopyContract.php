<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\Contracts\ContractReferenceGenerator;

class CopyContract
{
    public function __construct(private readonly ContractReferenceGenerator $references) {}

    /**
     * Identical copy of a contract, to be changed afterwards (an amendment, a
     * variant for the same recipient, another group contract…). The copy is a
     * new draft: its own reference, no archived PDF, no email history.
     */
    public function execute(Contract $source, string $userId): Contract
    {
        return Contract::create([
            ...$source->only([
                'contract_template_id',
                'contract_project_id',
                'target',
                'subject_type',
                'subject_id',
                'recipient_email',
                'title',
                'body_html',
                'signatories',
                'header_html',
                'footer_html',
                'header_height_mm',
                'footer_height_mm',
                'letterhead_scope',
                'letterhead_id',
                'font',
                'line_spacing',
                'data',
                'fill_fields',
                'fill_fields_status',
            ]),
            'reference' => $this->references->next(),
            'status' => ContractStatus::Draft,
            'version' => 1,
            'created_by' => $userId,
        ]);
    }
}
