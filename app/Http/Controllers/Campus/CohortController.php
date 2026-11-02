<?php

namespace App\Http\Controllers\Campus;

use App\Actions\Campus\AbandonCohortLearner;
use App\Actions\Campus\CloseCohort;
use App\Actions\Campus\CreateCohortLearner;
use App\Actions\Campus\EnrollCohortLearners;
use App\Actions\Campus\MoveCohortLearner;
use App\Enums\CohortStatus;
use App\Exports\CohortLearnerTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campus\AbandonCohortLearnerRequest;
use App\Http\Requests\Campus\EnrollCohortLearnersRequest;
use App\Http\Requests\Campus\ImportCohortLearnersRequest;
use App\Http\Requests\Campus\MoveCohortLearnerRequest;
use App\Http\Requests\Campus\RemoveCohortLearnersRequest;
use App\Http\Requests\Campus\StoreCohortLearnerRequest;
use App\Http\Requests\Campus\StoreCohortRequest;
use App\Http\Requests\Campus\UpdateCohortLearnerRequest;
use App\Http\Requests\Campus\UpdateCohortRequest;
use App\Imports\CohortLearnersImport;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\EducationLevel;
use App\Models\Learner;
use App\Services\Campus\CohortPaymentStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CohortController extends Controller
{
    public function index(Request $request): Response
    {
        $cohorts = Cohort::with(['campusFormation' => fn ($q) => $q->withTrashed()])
            ->withCount('learners')
            ->when($request->filled('formation'), fn ($q) => $q->where('campus_formation_id', $request->input('formation')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('started_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Campus/Cohorts/Index', [
            'cohorts' => $cohorts,
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statuses(withColor: true),
            'filters' => [
                'formation' => $request->input('formation', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Campus/Cohorts/Create', [
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name', 'duration_months']),
            'preselectedFormation' => $request->query('formation'),
        ]);
    }

    public function store(StoreCohortRequest $request): RedirectResponse
    {
        Cohort::create($request->validated());

        return redirect()->route('campus.cohorts.index')
            ->with('success', 'Cohorte créée avec succès.');
    }

    public function show(Cohort $cohort, CohortPaymentStats $stats): Response
    {
        $cohort->load(['campusFormation' => fn ($q) => $q->withTrashed()]);

        $enrolledIds = $cohort->learners()->pluck('learners.id');

        return Inertia::render('Campus/Cohorts/Show', [
            'cohort' => $cohort,
            'learners' => $cohort->learners()
                ->withPivot(['enrolled_at', 'status', 'abandon_motif', 'abandoned_at'])
                ->with('educationLevel')
                ->orderBy('last_name')
                ->paginate(10)
                ->withQueryString(),
            'paymentStats' => $stats->for($cohort),
            'availableLearners' => Learner::whereNotIn('id', $enrolledIds)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']),
            'educationLevels' => EducationLevel::orderBy('name')->get(['id', 'name']),
            'availableCohorts' => Cohort::with(['campusFormation' => fn ($q) => $q->withTrashed()->select('id', 'name', 'total_cost')])
                ->where('id', '!=', $cohort->id)
                ->where('status', '!=', CohortStatus::Cloturee->value)
                ->orderBy('name')
                ->get(['id', 'name', 'campus_formation_id'])
                ->map(fn (Cohort $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'formation_name' => $c->campusFormation->name ?? '(Formation supprimée)',
                    'total_cost' => $c->campusFormation->total_cost ?? 0,
                ]),
            'statuses' => $this->statuses(withColor: true),
        ]);
    }

    public function edit(Cohort $cohort): Response
    {
        return Inertia::render('Campus/Cohorts/Edit', [
            'cohort' => $cohort,
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statuses(withColor: false),
        ]);
    }

    public function update(UpdateCohortRequest $request, Cohort $cohort): RedirectResponse
    {
        $cohort->update($request->validated());

        return redirect()->route('campus.cohorts.index')
            ->with('success', 'Cohorte mise à jour.');
    }

    public function destroy(Cohort $cohort): RedirectResponse
    {
        $cohort->delete();

        return redirect()->route('campus.cohorts.index')
            ->with('success', 'Cohorte supprimée.');
    }

    public function storeLearner(StoreCohortLearnerRequest $request, Cohort $cohort, CreateCohortLearner $action): RedirectResponse
    {
        ['learner' => $learner, 'created' => $created] = $action->execute($cohort, $request->validated(), $request->file('photo'));

        return back()->with('success', $created
            ? 'Apprenant créé et inscrit dans la cohorte.'
            : "L'apprenant {$learner->first_name} {$learner->last_name} existe déjà et a été inscrit dans la cohorte.");
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        return Excel::download(new CohortLearnerTemplateExport, 'modele_import_cohorte.xlsx');
    }

    public function importLearners(ImportCohortLearnersRequest $request, Cohort $cohort): RedirectResponse
    {
        $import = new CohortLearnersImport($cohort);

        Excel::import($import, $request->file('file'));

        $message = "{$import->imported} apprenant(s) importé(s) et inscrit(s)";
        if ($import->skipped > 0) {
            $message .= ", {$import->skipped} ignoré(s) (doublons ou données manquantes)";
        }

        return back()->with('success', $message.'.');
    }

    public function enrollLearners(EnrollCohortLearnersRequest $request, Cohort $cohort, EnrollCohortLearners $action): RedirectResponse
    {
        $action->execute($cohort, $request->validated('learner_ids'));

        return back()->with('success', 'Apprenants inscrits avec succès.');
    }

    public function updateLearner(UpdateCohortLearnerRequest $request, Cohort $cohort, Learner $learner): RedirectResponse
    {
        $data = $request->safe()->except('photo');

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('learners/photos', 'public');
        }

        $learner->update($data);

        return back()->with('success', "Informations de l'apprenant mises à jour.");
    }

    public function removeLearner(Cohort $cohort, Learner $learner): RedirectResponse
    {
        $cohort->learners()->detach($learner->id);

        return back()->with('success', 'Apprenant retiré de la cohorte.');
    }

    public function abandonLearner(AbandonCohortLearnerRequest $request, Cohort $cohort, Learner $learner, AbandonCohortLearner $action): RedirectResponse
    {
        $action->execute($cohort, $learner, $request->validated('abandon_motif'));

        return back()->with(
            'success',
            "{$learner->first_name} {$learner->last_name} a été marqué(e) comme abandonné(e). Les frais restants ne sont plus à encaisser.",
        );
    }

    public function removeLearners(RemoveCohortLearnersRequest $request, Cohort $cohort): RedirectResponse
    {
        $learnerIds = $request->validated('learner_ids');

        $cohort->learners()->detach($learnerIds);

        return back()->with('success', count($learnerIds).' apprenant(s) retiré(s) de la cohorte.');
    }

    public function close(Cohort $cohort, CloseCohort $action): RedirectResponse
    {
        $action->execute($cohort);

        return redirect()->route('campus.cohorts.show', $cohort)
            ->with('success', 'Cohorte clôturée. Les apprenants actifs ont été marqués « Formation terminée ».');
    }

    public function moveLearner(MoveCohortLearnerRequest $request, Cohort $cohort, Learner $learner, MoveCohortLearner $action): RedirectResponse
    {
        $target = Cohort::findOrFail($request->validated('target_cohort_id'));

        ['transferred' => $transferred, 'cancelled' => $cancelled] = $action->execute($cohort, $learner, $target);

        $message = "{$learner->first_name} {$learner->last_name} déplacé vers « {$target->name} ».";
        if ($transferred > 0) {
            $message .= " {$transferred} versement(s) transféré(s).";
        }
        if ($cancelled > 0) {
            $message .= " {$cancelled} tranche(s) en attente annulée(s) (à replanifier dans la nouvelle cohorte).";
        }

        return back()->with('success', $message);
    }

    private function statuses(bool $withColor): Collection
    {
        return collect(CohortStatus::cases())->map(fn (CohortStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            ...($withColor ? ['color' => $status->color()] : []),
        ]);
    }
}
