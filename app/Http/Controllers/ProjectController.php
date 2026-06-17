<?php

namespace App\Http\Controllers;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\ProjectStatus;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Formation;
use App\Models\Partner;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'project'     => $project,
            'allPartners' => Partner::orderBy('name')->get(['id', 'name', 'logo_path']),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        $project->load('partners:id,name');

        return Inertia::render('Projects/Edit', [
            'project'  => array_merge($project->toArray(), [
                'started_at' => $project->started_at?->format('Y-m-d'),
                'ended_at'   => $project->ended_at?->format('Y-m-d'),
            ]),
            'statuses' => collect(ProjectStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function syncPartners(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'partner_ids'   => ['nullable', 'array'],
            'partner_ids.*' => ['uuid', 'exists:partners,id'],
        ]);

        $project->partners()->sync($request->input('partner_ids', []));

        return back()->with('success', 'Partenaires mis à jour.');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $partnerIds = $data['partner_ids'] ?? [];
        unset($data['partner_ids']);
        $oldStatus = $project->status;

        // Si le projet devient terminé
        if (isset($data['status']) && $data['status'] === ProjectStatus::Completed->value && $oldStatus !== ProjectStatus::Completed->value) {
            $data['ended_at'] = now();

            DB::transaction(function () use ($project) {
                // 1. Terminer automatiquement toutes les formations du projet
                $project->formations()
                    ->where('status', '!=', FormationStatus::Completed->value)
                    ->update([
                        'status' => FormationStatus::Completed->value,
                        'ended_at' => now(),
                    ]);

                // 2. Archiver automatiquement les formations terminées
                $project->formations()
                    ->where('status', FormationStatus::Completed->value)
                    ->update([
                        'status' => FormationStatus::Archived->value,
                    ]);

                // 3. Marquer tous les apprenants "en cours" comme "terminés" (diplômés)
                Formation::where('project_id', $project->id)
                    ->each(function ($formation) {
                        $formation->activeLearners()->updateExistingPivot(
                            $formation->activeLearners()->pluck('learners.id'),
                            [
                                'status' => LearnerStatus::Completed->value,
                                'completed_at' => now(),
                            ]
                        );
                    });
            });
        }

        // Si le projet devient archivé
        if (isset($data['status']) && $data['status'] === ProjectStatus::Archived->value && $oldStatus !== ProjectStatus::Archived->value) {
            $data['ended_at'] = now();

            DB::transaction(function () use ($project) {
                // 1. Terminer automatiquement toutes les formations du projet
                $project->formations()
                    ->where('status', '!=', FormationStatus::Completed->value)
                    ->update([
                        'status' => FormationStatus::Completed->value,
                        'ended_at' => now(),
                    ]);

                // 2. Archiver automatiquement toutes les formations
                $project->formations()
                    ->where('status', FormationStatus::Completed->value)
                    ->update([
                        'status' => FormationStatus::Archived->value,
                    ]);

                // 3. Marquer tous les apprenants "en cours" comme "terminés" (diplômés)
                Formation::where('project_id', $project->id)
                    ->each(function ($formation) {
                        $formation->activeLearners()->updateExistingPivot(
                            $formation->activeLearners()->pluck('learners.id'),
                            [
                                'status' => LearnerStatus::Completed->value,
                                'completed_at' => now(),
                            ]
                        );
                    });
            });
        }

        $project->update($data);
        $project->partners()->sync($partnerIds);

        // Générer un message avec le bilan si le projet vient d'être terminé
        if (isset($data['status']) && $data['status'] === ProjectStatus::Completed->value && $oldStatus !== ProjectStatus::Completed->value) {
            // Calculer les statistiques
            $totalLearners = $project->formations()->withCount('learners')->get()->sum('learners_count');
            $completedLearners = $project->formations()->with(['learners' => fn($q) => $q->wherePivot('status', LearnerStatus::Completed->value)])->get()->sum(fn($f) => $f->learners->count());

            return redirect()
                ->route('projects.show', $project)
                ->with('success', "Projet terminé avec succès. Bilan : {$completedLearners}/{$totalLearners} apprenants diplômés.");
        }

        // Générer un message avec le bilan si le projet vient d'être archivé
        if (isset($data['status']) && $data['status'] === ProjectStatus::Archived->value && $oldStatus !== ProjectStatus::Archived->value) {
            // Calculer les statistiques
            $totalLearners = $project->formations()->withCount('learners')->get()->sum('learners_count');
            $completedLearners = $project->formations()->with(['learners' => fn($q) => $q->wherePivot('status', LearnerStatus::Completed->value)])->get()->sum(fn($f) => $f->learners->count());

            return redirect()
                ->route('projects.show', $project)
                ->with('success', "Projet archivé avec succès. Bilan : {$completedLearners}/{$totalLearners} apprenants diplômés.");
        }

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
