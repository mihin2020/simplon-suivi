<?php

namespace App\Http\Controllers;

use App\Enums\LearnerStatus;
use App\Http\Requests\Learner\StoreLearnerRequest;
use App\Http\Requests\Learner\UpdateLearnerRequest;
use App\Models\AgeRange;
use App\Models\EducationLevel;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LearnerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Learner::class);

        $filters = request()->only([
            'search',
            'project_id',
            'formation_id',
        ]);

        $learners = Learner::query()
            ->with([
                // Used to display "Projet / Formation" columns (take first item in UI).
                'formations' => fn ($q) => $q
                    ->wherePivot('status', LearnerStatus::InProgress->value)
                    ->with('project:id,name')
                    ->orderByPivot('enrolled_at', 'desc'),
            ])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->search($s))
            ->when($filters['project_id'] ?? null, function ($q, $projectId) {
                $q->whereHas('formations', fn ($fq) => $fq->where('project_id', $projectId));
            })
            ->when($filters['formation_id'] ?? null, function ($q, $formationId) {
                $q->whereHas('formations', fn ($fq) => $fq->where('formations.id', $formationId));
            })
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Learners/Index', [
            'learners' => $learners,
            'filters'  => $filters,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'formations' => Formation::with('project:id,name')
                ->orderBy('name')
                ->get(['id', 'project_id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Learner::class);

        return Inertia::render('Learners/Create', [
            'educationLevels' => EducationLevel::orderBy('created_at')->get(),
            'ageRanges'       => AgeRange::orderBy('order')->orderBy('age_min')->get(),
        ]);
    }

    public function store(StoreLearnerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('learners', 'public');
            $data['photo_original_name'] = $request->file('photo')->getClientOriginalName();
        }
        if ($request->hasFile('cnib')) {
            $data['cnib_path'] = $request->file('cnib')->store('learners/cnib', 'public');
            $data['cnib_original_name'] = $request->file('cnib')->getClientOriginalName();
        }

        unset($data['photo'], $data['cnib']);

        $learner = Learner::create($data);

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Apprenant créé avec succès.');
    }

    public function show(Learner $learner): Response
    {
        $this->authorize('view', $learner);

        $learner->load([
            'educationLevel',
            'ageRange',
            'formations.project',
        ]);

        $insertionRecords = $learner->insertionRecords()
            ->with('recorder:id,first_name,last_name')
            ->orderBy('status_changed_at', 'desc')
            ->get()
            ->map(function ($record) {
                return array_merge($record->toArray(), [
                    'status_label' => $record->status->label(),
                    'status_color' => $record->status->color(),
                ]);
            });

        $latestInsertion = $insertionRecords->first();

        return Inertia::render('Learners/Show', [
            'learner' => $learner,
            'insertionRecords' => $insertionRecords,
            'latestInsertion' => $latestInsertion,
            'insertionStatuses' => collect(\App\Enums\InsertionStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'is_stage' => $s->isStage(),
                'is_employment' => $s->isEmployment(),
            ]),
        ]);
    }

    public function edit(Learner $learner): Response
    {
        $this->authorize('update', $learner);

        return Inertia::render('Learners/Edit', [
            'learner'         => $learner,
            'educationLevels' => EducationLevel::orderBy('created_at')->get(),
            'ageRanges'       => AgeRange::orderBy('order')->orderBy('age_min')->get(),
        ]);
    }

    public function update(UpdateLearnerRequest $request, Learner $learner): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($learner->photo_path) {
                Storage::disk('public')->delete($learner->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('learners', 'public');
            $data['photo_original_name'] = $request->file('photo')->getClientOriginalName();
        }
        if ($request->hasFile('cnib')) {
            if ($learner->cnib_path) {
                Storage::disk('public')->delete($learner->cnib_path);
            }
            $data['cnib_path'] = $request->file('cnib')->store('learners/cnib', 'public');
            $data['cnib_original_name'] = $request->file('cnib')->getClientOriginalName();
        }

        unset($data['photo'], $data['cnib']);

        $learner->update($data);

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Apprenant mis à jour avec succès.');
    }

    public function destroy(Learner $learner): RedirectResponse
    {
        $this->authorize('delete', $learner);

        $learner->delete();

        return redirect()
            ->route('learners.index')
            ->with('success', 'Apprenant supprimé.');
    }

    public function search()
    {
        $q = request('q');
        $formationId = request('formation_id');
        $projectId = request('project_id');

        $learners = Learner::query()
            ->select('id', 'first_name', 'last_name', 'email', 'phone')
            ->when($q, fn ($query) => $query->where(function ($sq) use ($q) {
                $sq->where('first_name', 'like', "%{$q}%")
                   ->orWhere('last_name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($formationId, fn ($query) => $query->whereHas('formations', fn ($fq) => $fq->where('formations.id', $formationId)))
            ->when($projectId, fn ($query) => $query->whereHas('formations', fn ($fq) => $fq->where('project_id', $projectId)))
            ->whereNotNull('email')
            ->orderBy('last_name')
            ->limit(100)
            ->get();

        return response()->json($learners);
    }
}
