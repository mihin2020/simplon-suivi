<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Partner;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::withCount('formations')
            ->orderByDesc('started_at')
            ->paginate(15);

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
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
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

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'formations' => fn ($q) => $q->withCount('activeLearners')->orderByDesc('started_at'),
            'partners:id,name,logo_path',
        ]);

        return Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        $project->load('partners:id,name');

        return Inertia::render('Projects/Edit', [
            'project'  => $project,
            'statuses' => collect(ProjectStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $partnerIds = $data['partner_ids'] ?? [];
        unset($data['partner_ids']);

        $project->update($data);
        $project->partners()->sync($partnerIds);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Projet mis à jour avec succès.');
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
                'id'   => $f->id,
                'name' => $f->name,
                'period' => $f->started_at?->format('d/m/Y') . ' - ' . $f->ended_at?->format('d/m/Y'),
            ]);

        return response()->json($formations);
    }
}
