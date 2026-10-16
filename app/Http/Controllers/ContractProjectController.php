<?php

namespace App\Http\Controllers;

use App\Actions\Contracts\DeleteContractProject;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Http\Requests\Contract\StoreContractProjectRequest;
use App\Http\Requests\Contract\UpdateContractProjectRequest;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\Trainer;
use App\Services\Contracts\ContractSubjectDirectory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ContractProjectController extends Controller
{
    public function __construct(private readonly ContractSubjectDirectory $subjects) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ContractProject::class);

        return Inertia::render('Contracts/Projects/Index', [
            'projects' => ContractProject::query()
                ->withCount('contracts')
                ->latest()
                ->paginate(24)
                ->through(fn (ContractProject $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'target_label' => $p->target->label(),
                    'contracts_count' => $p->contracts_count,
                    'created_at' => $p->created_at?->toDateString(),
                ]),
            'targets' => collect(ContractTarget::cases())
                ->map(fn (ContractTarget $t) => ['value' => $t->value, 'label' => $t->label()])
                ->all(),
        ]);
    }

    public function store(StoreContractProjectRequest $request): RedirectResponse
    {
        $project = ContractProject::create([...$request->validated(), 'created_by' => $request->user()->id]);

        Log::info('Contract project created', ['project_id' => $project->id, 'user_id' => $request->user()->id]);

        return redirect()->route('contract-projects.show', $project)->with('success', 'Projet créé. Rédigez maintenant le contrat de base.');
    }

    public function show(ContractProject $project): Response
    {
        $this->authorize('view', $project);

        $contracts = $project->contracts()
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([Trainer::class => ['user']])])
            ->oldest()
            ->get();

        return Inertia::render('Contracts/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'target' => $project->target->value,
                'target_label' => $project->target->label(),
            ],
            'contracts' => $contracts->map(fn (Contract $c) => [
                'id' => $c->id,
                'reference' => $c->reference,
                'title' => $c->title,
                'subject_id' => $c->subject_id,
                'subject_name' => $this->subjects->name($c->subject) ?: '-',
                'recipient_email' => $this->subjects->contractEmail($c),
                'is_nominative' => $this->subjects->isNominative($c),
                'emailed_at' => $c->emailed_at?->toIso8601String(),
                'emailed_to' => $c->emailed_to,
                'email_count' => $c->email_count,
                'email_status' => $c->email_status?->value,
                'status_label' => $c->status->label(),
                'status_color' => $c->status->color(),
                'fill_fields' => array_column($c->fill_fields ?? [], 'label'),
                'fill_fields_ready' => $c->fill_fields_status === FillFieldsStatus::Ready,
            ]),
            'subjects' => $this->subjects->options($project->target),
        ]);
    }

    public function update(UpdateContractProjectRequest $request, ContractProject $project): RedirectResponse
    {
        $project->update($request->validated());

        return back()->with('success', 'Projet mis à jour.');
    }

    public function destroy(ContractProject $project, DeleteContractProject $action): RedirectResponse
    {
        $this->authorize('delete', $project);

        $references = $action->execute($project);

        Log::info('Contract project deleted with its contracts', [
            'project_id' => $project->id,
            'references' => $references,
            'user_id' => request()->user()->id,
        ]);

        $count = count($references);
        $message = match ($count) {
            0 => 'Projet supprimé.',
            1 => 'Projet supprimé, ainsi que son contrat.',
            default => "Projet supprimé, ainsi que ses {$count} contrats.",
        };

        return redirect()->route('contract-projects.index')->with('success', $message);
    }
}
