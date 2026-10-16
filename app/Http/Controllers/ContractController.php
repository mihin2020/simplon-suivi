<?php

namespace App\Http\Controllers;

use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Enums\LetterheadScope;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Jobs\ExtractContractFields;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\Letterhead;
use App\Models\Partner;
use App\Models\Trainer;
use App\Services\Contracts\ContractPdfService;
use App\Services\Contracts\ContractReferenceGenerator;
use App\Services\Contracts\ContractSubjectDirectory;
use App\Services\Contracts\ContractTemplateImporter;
use App\Services\Contracts\LetterheadService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractTemplateImporter $importer,
        private readonly ContractSubjectDirectory $subjects,
        private readonly ContractReferenceGenerator $references,
        private readonly ContractPdfService $pdf,
        private readonly LetterheadService $letterheads,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = Contract::query()
            ->search($request->input('search'))
            ->target($request->input('target'))
            ->status($request->input('status'))
            ->emailStatus($request->input('email'))
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([Trainer::class => ['user']])])
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts->through(fn (Contract $c) => $this->toListItem($c)),
            'filters' => [
                'search' => $request->input('search'),
                'target' => $request->input('target'),
                'status' => $request->input('status'),
                'email' => $request->input('email'),
            ],
            'targets' => $this->targetOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Contract::class);

        // Opened from a project: the contract type is the project's.
        $project = $request->query('project') ? ContractProject::find($request->query('project')) : null;

        return Inertia::render('Contracts/Create', [
            'letterheads' => $this->letterheads->options(),
            'fonts' => ContractFont::options(),
            'lineSpacings' => ContractLineSpacing::options(),
            'subjects' => collect(ContractTarget::cases())
                ->mapWithKeys(fn (ContractTarget $target) => [$target->value => $this->subjects->options($target)])
                ->all(),
            'project' => $project ? ['id' => $project->id, 'name' => $project->name, 'target' => $project->target->value] : null,
            'targets' => $this->targetOptions(),
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $letterhead = isset($data['letterhead_id']) ? Letterhead::find($data['letterhead_id']) : null;
        $scope = LetterheadScope::from($data['letterhead_scope'] ?? LetterheadScope::AllPages->value);

        $target = ContractTarget::from($data['target']);

        // The recipient is optional: a contract may be drafted before the
        // person or partner exists in the application.
        $subject = $this->subjects->find($target, $data['subject_id'] ?? null);

        if (isset($data['subject_id']) && $subject === null) {
            return back()->withErrors(['subject_id' => 'Ce destinataire ne correspond pas au type de contrat.']);
        }

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
            'created_by' => $request->user()->id,
        ]);

        // Ready before anyone asks to generate this contract for other people.
        ExtractContractFields::dispatch($contract);

        Log::info('Contract generated', [
            'reference' => $contract->reference,
            'user_id' => $request->user()->id,
        ]);

        if ($contract->contract_project_id) {
            return redirect()
                ->route('contract-projects.show', $contract->contract_project_id)
                ->with('success', 'Contrat enregistré. Vous pouvez maintenant le générer pour d\'autres personnes.');
        }

        return redirect()
            ->route('contracts.show', $contract)
            ->with('success', 'Contrat enregistré. Il reste modifiable à tout moment.');
    }

    /**
     * Frozen rendering of the linked letterhead; empty when none is linked.
     * Zero height means that side of the letterhead has no image.
     *
     * @return array<string, mixed>
     */
    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load(['subject', 'project:id,name', 'creator:id,first_name,last_name']);

        return Inertia::render('Contracts/Show', [
            'contract' => $this->toDetail($contract),
            'subjects' => $this->subjects->options($contract->target),
        ]);
    }

    public function edit(Contract $contract): Response
    {
        $this->authorize('update', $contract);

        $contract->load(['subject', 'letterhead']);

        return Inertia::render('Contracts/Edit', [
            'contract' => $this->toDetail($contract),
            'letterheads' => $this->letterheads->options(),
            'fonts' => ContractFont::options(),
            'lineSpacings' => ContractLineSpacing::options(),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $data = $request->validated();

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

        // Keep the archived PDF in line with the text once the contract was finalized.
        if ($contract->pdf_path) {
            $contract->update(['pdf_path' => $this->pdf->store($contract)]);
        }

        return redirect()
            ->route('contracts.show', $contract)
            ->with('success', 'Contrat mis à jour.');
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

    public function finalize(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('finalize', $contract);

        $contract->update([
            'status' => ContractStatus::Finalized,
            'finalized_at' => now(),
        ]);

        // Archive the rendered PDF for this version.
        $path = $this->pdf->store($contract->fresh());
        $contract->update(['pdf_path' => $path]);

        Log::info('Contract finalized', [
            'contract_id' => $contract->id,
            'reference' => $contract->reference,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Contrat finalisé et archivé en PDF. Il reste modifiable.');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        $contract->delete();

        return redirect()
            ->route('contracts.index')
            ->with('success', 'Contrat supprimé.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Rows per page chosen in the list, limited to the offered values. */
    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 20);

        return in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'reference' => $contract->reference,
            'title' => $contract->title,
            'target' => $contract->target->value,
            'target_label' => $contract->target->label(),
            'status' => $contract->status->value,
            'status_label' => $contract->status->label(),
            'status_color' => $contract->status->color(),
            'subject_name' => $this->subjects->name($contract->subject) ?: '-',
            'recipient_email' => $this->subjects->contractEmail($contract),
            'is_nominative' => $this->subjects->isNominative($contract),
            'emailed_at' => $contract->emailed_at?->toIso8601String(),
            'emailed_to' => $contract->emailed_to,
            'email_count' => $contract->email_count,
            'email_status' => $contract->email_status?->value,
            'created_at' => $contract->created_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Contract $contract): array
    {
        return array_merge($this->toListItem($contract), [
            'body_html' => $contract->body_html,
            'signatories' => $contract->signatories,
            // Page geometry of the frozen letterhead, for the paged editor.
            'header_height_mm' => $contract->header_height_mm,
            'footer_height_mm' => $contract->footer_height_mm,
            'letterhead_scope' => $contract->letterhead_scope?->value,
            'letterhead_id' => $contract->letterhead_id,
            'font' => ($contract->font ?? ContractFont::default())->value,
            'line_spacing' => ($contract->line_spacing ?? ContractLineSpacing::default())->value,
            'version' => $contract->version,
            'project' => $contract->project ? ['id' => $contract->project->id, 'name' => $contract->project->name] : null,
            'subject_id' => $contract->subject_id,
            // The address entered on the contract, and the one actually used (record fallback).
            'recipient_email' => $contract->recipient_email,
            'sent_to_email' => $this->subjects->contractEmail($contract),
            'subject_email' => $this->subjects->email($contract->subject),
            'emailed_at' => $contract->emailed_at?->toIso8601String(),
            'emailed_to' => $contract->emailed_to,
            'email_count' => $contract->email_count,
            'email_status' => $contract->email_status?->value,
            'fill_fields' => array_column($contract->fill_fields ?? [], 'label'),
            'fill_fields_ready' => $contract->fill_fields_status === FillFieldsStatus::Ready,
            'pdf_url' => $contract->pdf_path ? Storage::disk('public')->url($contract->pdf_path) : null,
            'created_by' => $contract->creator?->full_name,
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function targetOptions(): array
    {
        return collect(ContractTarget::cases())
            ->map(fn (ContractTarget $t) => ['value' => $t->value, 'label' => $t->label(), 'color' => $t->color()])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function statusOptions(): array
    {
        return collect(ContractStatus::cases())
            ->map(fn (ContractStatus $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->all();
    }
}
