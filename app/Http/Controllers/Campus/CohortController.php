<?php

namespace App\Http\Controllers\Campus;

use App\Enums\CohortStatus;
use App\Exports\CohortLearnerTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\EducationLevel;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CohortController extends Controller
{
    public function index(): Response
    {
        $cohorts = Cohort::with('campusFormation')
            ->withCount('learners')
            ->orderBy('started_at', 'desc')
            ->paginate(15);

        return Inertia::render('Campus/Cohorts/Index', [
            'cohorts'    => $cohorts,
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name']),
            'statuses'   => collect(CohortStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Campus/Cohorts/Create', [
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campus_formation_id' => ['required', 'uuid', 'exists:campus_formations,id'],
            'name'                => ['required', 'string', 'max:255'],
            'started_at'          => ['required', 'date'],
            'ended_at'            => ['required', 'date', 'after:started_at'],
        ]);

        Cohort::create($data);

        return redirect()->route('campus.cohorts.index')
            ->with('success', 'Cohorte créée avec succès.');
    }

    public function show(Cohort $cohort): Response
    {
        $cohort->load('campusFormation');

        $enrolledIds = $cohort->learners()->pluck('learners.id');

        $learners = $cohort->learners()
            ->withPivot(['enrolled_at', 'status'])
            ->with('educationLevel')
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        $paymentStats = [
            'total_collected' => $cohort->total_collected,
            'total_expected'  => $cohort->total_expected,
            'total_remaining' => $cohort->total_remaining,
            'paid_count'      => $cohort->payments()->where('status', 'paye')->distinct('learner_id')->count(),
            'overdue_count'   => $cohort->payments()->overdue()->distinct('learner_id')->count(),
        ];

        return Inertia::render('Campus/Cohorts/Show', [
            'cohort'            => $cohort,
            'learners'          => $learners,
            'paymentStats'      => $paymentStats,
            'availableLearners'  => Learner::whereNotIn('id', $enrolledIds)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']),
            'educationLevels'    => EducationLevel::orderBy('name')->get(['id', 'name']),
            'statuses'           => collect(CohortStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
        ]);
    }

    public function edit(Cohort $cohort): Response
    {
        return Inertia::render('Campus/Cohorts/Edit', [
            'cohort'     => $cohort,
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name']),
            'statuses'   => collect(CohortStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(Request $request, Cohort $cohort): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at'   => ['required', 'date', 'after:started_at'],
            'status'     => ['required', 'in:planifiee,en_cours,cloturee'],
        ]);

        $cohort->update($data);

        return redirect()->route('campus.cohorts.index')
            ->with('success', 'Cohorte mise à jour.');
    }

    public function destroy(Cohort $cohort): RedirectResponse
    {
        $cohort->delete();

        return redirect()->route('campus.cohorts.index')
            ->with('success', 'Cohorte supprimée.');
    }

    public function storeLearner(Request $request, Cohort $cohort): RedirectResponse
    {
        $data = $request->validate([
            'last_name'                   => ['required', 'string', 'max:100'],
            'first_name'                  => ['required', 'string', 'max:100'],
            'email'                       => ['nullable', 'email', 'max:255'],
            'phone'                       => ['nullable', 'string', 'max:30'],
            'gender'                      => ['nullable', 'in:male,female'],
            'birth_date'                  => ['nullable', 'date'],
            'education_level_id'          => ['nullable', 'exists:education_levels,id'],
            'emergency_contact_name'      => ['nullable', 'string', 'max:100'],
            'emergency_contact_firstname' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone'     => ['nullable', 'string', 'max:30'],
            'photo'                       => ['nullable', 'image', 'max:2048'],
        ]);

        // Si l'email existe déjà, on inscrit l'apprenant existant sans créer de doublon
        if (!empty($data['email'])) {
            $existing = Learner::where('email', $data['email'])->first();

            if ($existing) {
                $cohort->learners()->syncWithoutDetaching([
                    $existing->id => ['enrolled_at' => now(), 'status' => 'actif'],
                ]);

                return back()->with('success', "L'apprenant {$existing->first_name} {$existing->last_name} existe déjà et a été inscrit dans la cohorte.");
            }
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('learners/photos', 'public');
        }
        unset($data['photo']);

        $learner = Learner::create($data);

        $cohort->learners()->syncWithoutDetaching([
            $learner->id => ['enrolled_at' => now(), 'status' => 'actif'],
        ]);

        return back()->with('success', 'Apprenant créé et inscrit dans la cohorte.');
    }

    public function downloadImportTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(new CohortLearnerTemplateExport(), 'modele_import_cohorte.xlsx');
    }

    public function importLearners(Request $request, Cohort $cohort): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $import = new \App\Imports\CohortLearnersImport($cohort);

        Excel::import($import, $request->file('file'));

        $msg = "{$import->imported} apprenant(s) importé(s) et inscrit(s)";
        if ($import->skipped > 0) {
            $msg .= ", {$import->skipped} ignoré(s) (doublons ou données manquantes)";
        }

        return back()->with('success', $msg . '.');
    }

    public function enrollLearners(Request $request, Cohort $cohort): RedirectResponse
    {
        $request->validate([
            'learner_ids'   => ['required', 'array'],
            'learner_ids.*' => ['uuid', 'exists:learners,id'],
        ]);

        $cohort->learners()->syncWithoutDetaching(
            collect($request->learner_ids)->mapWithKeys(fn($id) => [
                $id => ['enrolled_at' => now(), 'status' => 'actif'],
            ])->all()
        );

        return back()->with('success', 'Apprenants inscrits avec succès.');
    }

    public function updateLearner(Request $request, Cohort $cohort, Learner $learner): RedirectResponse
    {
        $data = $request->validate([
            'last_name'                   => ['required', 'string', 'max:100'],
            'first_name'                  => ['required', 'string', 'max:100'],
            'email'                       => ['nullable', 'email', 'max:255', 'unique:learners,email,' . $learner->id],
            'phone'                       => ['nullable', 'string', 'max:30'],
            'gender'                      => ['nullable', 'in:male,female'],
            'birth_date'                  => ['nullable', 'date'],
            'education_level_id'          => ['nullable', 'exists:education_levels,id'],
            'emergency_contact_name'      => ['nullable', 'string', 'max:100'],
            'emergency_contact_firstname' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone'     => ['nullable', 'string', 'max:30'],
            'photo'                       => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('learners/photos', 'public');
        }
        unset($data['photo']);

        $learner->update($data);

        return back()->with('success', "Informations de l'apprenant mises à jour.");
    }

    public function removeLearner(Cohort $cohort, Learner $learner): RedirectResponse
    {
        $cohort->learners()->detach($learner->id);

        return back()->with('success', 'Apprenant retiré de la cohorte.');
    }

    public function removeLearners(Request $request, Cohort $cohort): RedirectResponse
    {
        $request->validate([
            'learner_ids'   => ['required', 'array', 'min:1'],
            'learner_ids.*' => ['uuid', 'exists:learners,id'],
        ]);

        $cohort->learners()->detach($request->learner_ids);

        $count = count($request->learner_ids);

        return back()->with('success', "{$count} apprenant(s) retiré(s) de la cohorte.");
    }

    public function close(Cohort $cohort): RedirectResponse
    {
        $cohort->update(['status' => CohortStatus::Cloturee]);

        return redirect()->route('campus.cohorts.show', $cohort)
            ->with('success', 'Cohorte clôturée.');
    }
}
