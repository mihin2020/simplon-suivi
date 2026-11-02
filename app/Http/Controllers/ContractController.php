<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\CreateContract;
use App\Actions\Contracts\UpdateContract;
use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\Letterhead;
use App\Models\Trainer;
use App\Services\Contracts\ContractPdfService;
use App\Services\Contracts\ContractSubjectDirectory;
use App\Services\Contracts\LetterheadService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractSubjectDirectory $subjects,
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

    public function store(StoreContractRequest $request, CreateContract $action): RedirectResponse
    {
        $contract = $action->execute($request->validated(), $request->user());

        if ($contract->contract_project_id) {
            return redirect()
                ->route('contract-projects.show', $contract->contract_project_id)
                ->with('success', 'Contrat enregistré. Vous pouvez maintenant le générer pour d\'autres personnes.');
        }

        return redirect()
            ->route('contracts.show', $contract)
            ->with('success', 'Contrat enregistré. Il reste modifiable à tout moment.');
    }

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

    public function update(UpdateContractRequest $request, Contract $contract, UpdateContract $action): RedirectResponse
    {
        $action->execute($contract, $request->validated());

        return redirect()
            ->route('contracts.show', $contract)
            ->with('success', 'Contrat mis à jour.');
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
