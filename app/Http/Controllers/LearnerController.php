<?php

namespace App\Http\Controllers;

use App\Http\Requests\Learner\StoreLearnerRequest;
use App\Http\Requests\Learner\UpdateLearnerRequest;
use App\Models\EducationLevel;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LearnerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Learner::class);

        $learners = Learner::with('educationLevel')
            ->when(request('search'), fn ($q, $s) => $q->search($s))
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Learners/Index', [
            'learners' => $learners,
            'filters'  => request()->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Learner::class);

        return Inertia::render('Learners/Create', [
            'educationLevels' => EducationLevel::orderBy('order')->get(),
        ]);
    }

    public function store(StoreLearnerRequest $request): RedirectResponse
    {
        $learner = Learner::create($request->validated());

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Apprenant créé avec succès.');
    }

    public function show(Learner $learner): Response
    {
        $this->authorize('view', $learner);

        $learner->load([
            'educationLevel',
            'formations.project',
        ]);

        return Inertia::render('Learners/Show', [
            'learner' => $learner,
        ]);
    }

    public function edit(Learner $learner): Response
    {
        $this->authorize('update', $learner);

        return Inertia::render('Learners/Edit', [
            'learner'         => $learner,
            'educationLevels' => EducationLevel::orderBy('order')->get(),
        ]);
    }

    public function update(UpdateLearnerRequest $request, Learner $learner): RedirectResponse
    {
        $learner->update($request->validated());

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
}
