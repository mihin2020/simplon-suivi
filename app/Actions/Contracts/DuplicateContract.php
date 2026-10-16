<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\Contracts\ContractPersonalizer;
use App\Services\Contracts\ContractReferenceGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateContract
{
    public function __construct(
        private readonly ContractPersonalizer $personalizer,
        private readonly ContractReferenceGenerator $references,
    ) {}

    /**
     * Copy a contract for other recipients: same text adapted to each
     * identity, same letterhead, same project. Copies start as drafts.
     * All or nothing, so a failure cannot leave half a batch behind.
     *
     * @param  Collection<int, Model>  $subjects
     * @return Collection<int, Contract>
     */
    public function execute(Contract $source, Collection $subjects, string $userId): Collection
    {
        return DB::transaction(fn () => $subjects->map(fn (Model $subject) => Contract::create([
            'reference' => $this->references->next(),
            'contract_project_id' => $source->contract_project_id,
            'target' => $source->target,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'title' => $source->title,
            'body_html' => $this->personalizer->adapt((string) $source->body_html, $source->target, $source->subject, $subject),
            'signatories' => $source->signatories
                ? array_map(fn (string $text) => html_entity_decode($this->personalizer->adapt(e($text), $source->target, $source->subject, $subject), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $source->signatories)
                : null,
            'header_html' => $source->header_html,
            'footer_html' => $source->footer_html,
            'header_height_mm' => $source->header_height_mm,
            'footer_height_mm' => $source->footer_height_mm,
            'letterhead_scope' => $source->letterhead_scope,
            'letterhead_id' => $source->letterhead_id,
            'font' => $source->font,
            'line_spacing' => $source->line_spacing,
            'data' => [],
            'status' => ContractStatus::Draft,
            'version' => 1,
            'created_by' => $userId,
        ])));
    }
}
