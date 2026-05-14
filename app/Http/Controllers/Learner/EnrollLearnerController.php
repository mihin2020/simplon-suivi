<?php

namespace App\Http\Controllers\Learner;

use App\Actions\EnrollLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\EnrollLearnerRequest;
use App\Http\Requests\Learner\StoreLearnerRequest;
use App\Models\AgeRange;
use App\Models\EducationLevel;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EnrollLearnerController extends Controller
{
    /** Page : sélectionner un apprenant existant */
    public function create(Formation $formation): Response
    {
        $this->authorize('update', $formation);

        $formation->load('project');

        $enrolledIds = $formation->learners()->pluck('learners.id');

        $learners = Learner::whereNotIn('id', $enrolledIds)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return Inertia::render('Formations/Enroll', [
            'formation' => $formation,
            'learners'  => $learners,
        ]);
    }

    /** Action : inscrire un apprenant existant */
    public function store(EnrollLearnerRequest $request, Formation $formation, EnrollLearner $action): RedirectResponse
    {
        $learner = Learner::findOrFail($request->validated('learner_id'));

        $action->execute($formation, $learner);

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', "{$learner->full_name} a été inscrit(e) à la formation.");
    }

    /** Page : créer un nouvel apprenant et l'inscrire directement */
    public function createLearner(Formation $formation): Response
    {
        $this->authorize('update', $formation);

        $formation->load('project');

        return Inertia::render('Formations/CreateLearner', [
            'formation'       => $formation,
            'educationLevels' => EducationLevel::orderBy('created_at')->get(),
            'ageRanges'       => AgeRange::orderBy('order')->orderBy('age_min')->get(),
        ]);
    }

    /** Action : créer + inscrire */
    public function storeLearner(StoreLearnerRequest $request, Formation $formation, EnrollLearner $action): RedirectResponse
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
        $action->execute($formation, $learner);

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', "{$learner->full_name} créé(e) et inscrit(e) à la formation.");
    }
}
