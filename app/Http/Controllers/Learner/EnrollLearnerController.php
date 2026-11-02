<?php

namespace App\Http\Controllers\Learner;

use App\Actions\EnrollLearner;
use App\Actions\EnrollLearners;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\EnrollLearnerRequest;
use App\Http\Requests\Learner\StoreLearnerRequest;
use App\Models\AgeRange;
use App\Models\EducationLevel;
use App\Models\Formation;
use App\Models\LastDiploma;
use App\Models\Learner;
use App\Models\Vulnerability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EnrollLearnerController extends Controller
{
    /** Page : sélectionner un ou plusieurs apprenants existants */
    public function create(Formation $formation): Response
    {
        $this->authorize('update', $formation);

        $formation->load('project');

        $learners = Learner::whereDoesntHave('formations', fn ($query) => $query->whereKey($formation->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return Inertia::render('Formations/Enroll', [
            'formation' => $formation,
            'learners' => $learners,
            'remainingSeats' => $formation->capacity === null
                ? null
                : max(0, $formation->capacity - $formation->activeLearners()->count()),
        ]);
    }

    /** Action : inscrire un ou plusieurs apprenants existants */
    public function store(EnrollLearnerRequest $request, Formation $formation, EnrollLearners $action): RedirectResponse
    {
        $count = $action->execute($formation, $request->validated('learner_ids'));

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', $count === 1
                ? '1 apprenant a été inscrit(e) à la formation.'
                : "{$count} apprenants ont été inscrits à la formation.");
    }

    /** Page : créer un nouvel apprenant et l'inscrire directement */
    public function createLearner(Formation $formation): Response
    {
        $this->authorize('update', $formation);

        $formation->load('project');

        return Inertia::render('Formations/CreateLearner', [
            'formation' => $formation,
            'educationLevels' => EducationLevel::orderBy('created_at')->get(),
            'ageRanges' => AgeRange::orderBy('order')->orderBy('age_min')->get(),
            'vulnerabilities' => Vulnerability::orderBy('created_at')->get(),
            'lastDiplomas' => LastDiploma::orderBy('created_at')->get(),
        ]);
    }

    /** Action : créer + inscrire */
    public function storeLearner(StoreLearnerRequest $request, Formation $formation, EnrollLearner $action): RedirectResponse
    {
        // Refuse before creating anything: a refused enrolment must not leave a learner behind.
        $action->ensureEnrollable($formation);

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('learners', 'public');
            $data['photo_original_name'] = $request->file('photo')->getClientOriginalName();
        }
        if ($request->hasFile('cnib')) {
            $data['cnib_path'] = $request->file('cnib')->store('learners/cnib', 'local');
            $data['cnib_original_name'] = $request->file('cnib')->getClientOriginalName();
        }
        if ($request->hasFile('cv')) {
            $data['cv_path'] = $request->file('cv')->store('learners/cv', 'local');
            $data['cv_original_name'] = $request->file('cv')->getClientOriginalName();
        }
        unset($data['photo'], $data['cnib'], $data['cv']);

        $learner = DB::transaction(function () use ($data, $formation, $action) {
            $learner = Learner::create($data);
            $action->execute($formation, $learner);

            return $learner;
        });

        return redirect()
            ->route('formations.show', $formation)
            ->with('success', "{$learner->full_name} créé(e) et inscrit(e) à la formation.");
    }
}
