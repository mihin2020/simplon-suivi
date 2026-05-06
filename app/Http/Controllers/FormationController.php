<?php

namespace App\Http\Controllers;

use App\Enums\FormationStatus;
use App\Http\Requests\Formation\StoreFormationRequest;
use App\Http\Requests\Formation\UpdateFormationRequest;
use App\Models\Formation;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FormationController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('viewAny', Formation::class);

        $formations = $project->formations()
            ->withCount('activeLearners')
            ->orderByDesc('started_at')
            ->paginate(15);

        return Inertia::render('Formations/Index', [
            'project'    => $project,
            'formations' => $formations,
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('create', Formation::class);

        return Inertia::render('Formations/Create', [
            'project'  => $project,
            'statuses' => collect(FormationStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(StoreFormationRequest $request, Project $project): RedirectResponse
    {
        $formation = $project->formations()->create($request->validated());

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', 'Formation créée avec succès.');
    }

    public function show(Formation $formation): Response
    {
        $this->authorize('view', $formation);

        $formation->load([
            'project',
            'trainers.user',
            'activeLearners.educationLevel',
        ]);

        return Inertia::render('Formations/Show', [
            'formation' => $formation,
        ]);
    }

    public function edit(Formation $formation): Response
    {
        $this->authorize('update', $formation);

        return Inertia::render('Formations/Edit', [
            'formation' => $formation->load('project'),
            'statuses'  => collect(FormationStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(UpdateFormationRequest $request, Formation $formation): RedirectResponse
    {
        $formation->update($request->validated());

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', 'Formation mise à jour avec succès.');
    }

    public function destroy(Formation $formation): RedirectResponse
    {
        $this->authorize('delete', $formation);

        $formation->delete();

        return redirect()
            ->route('projects.show', $formation->project_id)
            ->with('success', 'Formation supprimée.');
    }
}
