<?php

namespace App\Http\Controllers;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\ProjectStatus;
use App\Http\Requests\Formation\StoreFormationRequest;
use App\Http\Requests\Formation\UpdateFormationRequest;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use App\Models\Referentiel;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FormationController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('viewAny', Formation::class);

        $formations = $project->formations()
            ->withCount(['learners as active_learners_count' => fn ($q) =>
                $q->where('formation_learner.status', LearnerStatus::InProgress->value)
            ])
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
            'project'       => $project,
            'statuses'      => collect(FormationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'referentiels'  => Referentiel::orderBy('name')->get(['id', 'name']),
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
            'referentiel',
        ]);

        $activeLearners = $formation->activeLearners()
            ->with('educationLevel')
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        $inactiveLearners = $formation->inactiveLearners()
            ->with('educationLevel')
            ->orderBy('last_name')
            ->get();

        // Formateurs non assignés à cette formation
        $assignedTrainerIds = $formation->trainers->pluck('id');
        $availableTrainers = Trainer::with('user')
            ->whereNotIn('id', $assignedTrainerIds)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'user_id']);

        return Inertia::render('Formations/Show', [
            'formation'        => $formation,
            'activeLearners'   => $activeLearners,
            'inactiveLearners' => $inactiveLearners,
            'availableTrainers' => $availableTrainers,
            'referentiels'     => Referentiel::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Formation $formation): Response
    {
        $this->authorize('update', $formation);

        return Inertia::render('Formations/Edit', [
            'formation'    => $formation->load('project'),
            'statuses'     => collect(FormationStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'referentiels' => Referentiel::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function assignReferentiel(Request $request, Formation $formation): RedirectResponse
    {
        $this->authorize('update', $formation);

        $request->validate([
            'referentiel_id' => ['nullable', 'uuid', 'exists:referentiels,id'],
        ]);

        $formation->update(['referentiel_id' => $request->input('referentiel_id')]);

        return back()->with('success', 'Référentiel assigné avec succès.');
    }

    public function update(UpdateFormationRequest $request, Formation $formation): RedirectResponse
    {
        $data = $request->validated();
        $oldStatus = $formation->status;

        // Si la formation devient terminée
        if (isset($data['status']) && $data['status'] === FormationStatus::Completed->value && $oldStatus !== FormationStatus::Completed->value) {
            // Mettre à jour la date de fin automatiquement
            $data['ended_at'] = now();

            // Marquer tous les apprenants "en cours" comme "terminés" (diplômés)
            $formation->activeLearners()->updateExistingPivot(
                $formation->activeLearners()->pluck('learners.id'),
                [
                    'status' => LearnerStatus::Completed->value,
                    'completed_at' => now(),
                ]
            );
        }

        $formation->update($data);

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
