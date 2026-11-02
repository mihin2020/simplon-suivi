<?php

namespace App\Http\Controllers;

use App\Actions\Projects\UpdateProject;
use App\Enums\ProjectStatus;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\SyncProjectPartnersRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use App\Services\FormationLearnerCounts;
use App\Services\ProgressCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        $calculator = app(ProgressCalculator::class);

        $projects = Project::withCount('formations')
            ->with([
                'phases:id,project_id,weight',
                'phases.activities:id,phase_id,weight',
                'phases.activities.tasks:id,activity_id,status',
            ])
            ->orderByDesc('started_at')
            ->paginate(15);

        $projects->getCollection()->transform(function (Project $project) use ($calculator) {
            $project->setAttribute('progress_percentage', $calculator->forProject($project));
            $project->unsetRelation('phases');

            return $project;
        });

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'statuses' => collect(ProjectStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projects/Create', [
            'statuses' => collect(ProjectStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'partners' => Partner::orderBy('name')->get(['id', 'name', 'category']),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $partnerIds = $data['partner_ids'] ?? [];
        unset($data['partner_ids']);

        $project = Project::create($data);
        $project->partners()->sync($partnerIds);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Projet créé avec succès.');
    }

    public function show(Project $project, ProgressCalculator $calculator, FormationLearnerCounts $counter): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'formations' => fn ($q) => $q->orderByDesc('started_at'),
            'partners:id,name,logo_path,category',
        ]);

        $counts = $counter->for($project->formations->pluck('id')->all());
        foreach ($project->formations as $formation) {
            $formation->setAttribute('learners_count', $counts[$formation->id]['total']);
            $formation->setAttribute('active_learners_count', $counts[$formation->id]['in_progress']);
        }

        $calculator->hydrate($project);

        $assignableUsers = request()->user()?->can('update', $project)
            ? User::query()
                ->assignableToPlanning()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email', 'role'])
            : collect();

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'allPartners' => Partner::orderBy('name')->get(['id', 'name', 'logo_path', 'category']),
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        $project->load('partners:id,name,category');

        return Inertia::render('Projects/Edit', [
            'project' => array_merge($project->toArray(), [
                'started_at' => $project->started_at?->format('Y-m-d'),
                'ended_at' => $project->ended_at?->format('Y-m-d'),
            ]),
            'statuses' => collect(ProjectStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'partners' => Partner::orderBy('name')->get(['id', 'name', 'category']),
        ]);
    }

    public function syncPartners(SyncProjectPartnersRequest $request, Project $project): RedirectResponse
    {
        $project->partners()->sync($request->validated('partner_ids', []));

        return back()->with('success', 'Partenaires mis à jour.');
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $action, FormationLearnerCounts $counter): RedirectResponse
    {
        $closing = $action->execute($project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', $closing ? $this->closingMessage($project, $closing, $counter) : 'Projet mis à jour avec succès.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Projet supprimé.');
    }

    /**
     * Récupère les formations d'un projet en JSON (pour le sélecteur dynamique)
     */
    public function formationsJson(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $formations = $project->formations()
            ->select('id', 'name', 'started_at', 'ended_at', 'status')
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'period' => $f->started_at?->format('d/m/Y').' - '.$f->ended_at?->format('d/m/Y'),
            ]);

        return response()->json($formations);
    }

    /** Summary shown when a project has just been completed or archived: learners who finished out of all enrolled. */
    private function closingMessage(Project $project, ProjectStatus $status, FormationLearnerCounts $counter): string
    {
        $counts = $counter->for($project->formations()->pluck('id')->all());
        $total = array_sum(array_column($counts, 'total'));
        $completed = array_sum(array_column($counts, 'completed'));
        $verb = $status === ProjectStatus::Completed ? 'terminé' : 'archivé';

        return "Projet {$verb} avec succès. Bilan : {$completed}/{$total} apprenants ayant terminé la formation.";
    }
}
