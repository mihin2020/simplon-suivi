<?php

namespace App\Http\Controllers\Campus;

use App\Enums\CohortStatus;
use App\Exports\CohortLearnerTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\EducationLevel;
use App\Models\Learner;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CohortController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Cohort::with(['campusFormation' => fn ($q) => $q->withTrashed()])
            ->withCount('learners')
            ->orderBy('started_at', 'desc');

        if ($request->filled('formation')) {
            $query->where('campus_formation_id', $request->input('formation'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $cohorts = $query->paginate(15)->withQueryString();

        return Inertia::render('Campus/Cohorts/Index', [
            'cohorts'    => $cohorts,
            'formations' => CampusFormation::active()->orderBy('name')->get(['id', 'name']),
            'statuses'   => collect(CohortStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'filters'    => [
                'formation' => $request->input('formation', ''),
                'status'    => $request->input('status', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Campus/Cohorts/Create', [
            'formations'           => CampusFormation::active()->orderBy('name')->get(['id', 'name', 'duration_months']),
            'preselectedFormation' => $request->query('formation'),
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
        $cohort->load(['campusFormation' => fn ($q) => $q->withTrashed()]);

        $enrolledIds = $cohort->learners()->pluck('learners.id');

        $learners = $cohort->learners()
            ->withPivot(['enrolled_at', 'status'])
            ->with('educationLevel')
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        $unitCost        = $cohort->campusFormation?->total_cost ?? 0;
        $totalCollected  = $cohort->total_collected;
        $totalExpected   = $cohort->total_expected;

        // Soldés : apprenants ayant payé l'intégralité du coût de la formation
        $paidPerLearner = $cohort->payments()
            ->where('status', 'paye')
            ->selectRaw('learner_id, SUM(amount) as total_paid')
            ->groupBy('learner_id')
            ->pluck('total_paid', 'learner_id');

        $fullyPaidCount = $unitCost > 0
            ? $paidPerLearner->filter(fn ($paid) => $paid >= $unitCost)->count()
            : 0;

        // En retard : apprenants avec au moins une échéance dépassée ET non payée
        $overdueCount = $cohort->payments()
            ->overdue()
            ->distinct('learner_id')
            ->count('learner_id');

        $paymentStats = [
            'total_collected' => $totalCollected,
            'total_expected'  => $totalExpected,
            'total_remaining' => max(0, $totalExpected - $totalCollected),
            'paid_count'      => $fullyPaidCount,
            'overdue_count'   => $overdueCount,
        ];

        return Inertia::render('Campus/Cohorts/Show', [
            'cohort'            => $cohort,
            'learners'          => $learners,
            'paymentStats'      => $paymentStats,
            'availableLearners'  => Learner::whereNotIn('id', $enrolledIds)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']),
            'educationLevels'    => EducationLevel::orderBy('name')->get(['id', 'name']),
            'availableCohorts'   => Cohort::with(['campusFormation' => fn ($q) => $q->withTrashed()->select('id', 'name', 'total_cost')])
                ->where('id', '!=', $cohort->id)
                ->where('status', '!=', CohortStatus::Cloturee->value)
                ->orderBy('name')
                ->get(['id', 'name', 'campus_formation_id'])
                ->map(fn($c) => [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'formation_name' => $c->campusFormation?->name ?? '(Formation supprimée)',
                    'total_cost'     => $c->campusFormation?->total_cost ?? 0,
                ]),
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
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de modifier une cohorte clôturée.']);
        }
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
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de modifier une cohorte clôturée.']);
        }
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
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de modifier une cohorte clôturée.']);
        }
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
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de modifier une cohorte clôturée.']);
        }

        $cohort->learners()->detach($learner->id);

        return back()->with('success', 'Apprenant retiré de la cohorte.');
    }

    public function removeLearners(Request $request, Cohort $cohort): RedirectResponse
    {
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de modifier une cohorte clôturée.']);
        }
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
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Cette cohorte est déjà clôturée.']);
        }

        DB::table('cohort_learner')
            ->where('cohort_id', $cohort->id)
            ->where('status', 'actif')
            ->update(['status' => 'diplome']);

        $cohort->update(['status' => CohortStatus::Cloturee]);

        return redirect()->route('campus.cohorts.show', $cohort)
            ->with('success', 'Cohorte clôturée. Les apprenants actifs ont été diplômés.');
    }

    public function moveLearner(Request $request, Cohort $cohort, Learner $learner): RedirectResponse
    {
        $data = $request->validate([
            'target_cohort_id' => ['required', 'uuid', 'exists:cohorts,id'],
        ]);

        if ($data['target_cohort_id'] === $cohort->id) {
            return back()->withErrors(['target_cohort_id' => 'La cohorte cible doit être différente de la cohorte source.']);
        }

        $pivot = DB::table('cohort_learner')
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->first();

        if (! $pivot || $pivot->status !== 'actif') {
            return back()->withErrors(['learner' => 'Cet apprenant n\'est pas actif dans cette cohorte.']);
        }

        $targetCohort = Cohort::findOrFail($data['target_cohort_id']);

        if ($targetCohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['target_cohort_id' => 'La cohorte cible est clôturée.']);
        }

        // Mark deplace in source cohort
        DB::table('cohort_learner')
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->update(['status' => 'deplace']);

        // Enroll as actif in target cohort (ignore if already present)
        DB::table('cohort_learner')->insertOrIgnore([
            'cohort_id'   => $targetCohort->id,
            'learner_id'  => $learner->id,
            'enrolled_at' => now(),
            'status'      => 'actif',
        ]);

        // Transfer only paid payments — they represent real money received
        $transferred = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->where('status', 'paye')
            ->count();

        Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->where('status', 'paye')
            ->update(['cohort_id' => $targetCohort->id]);

        // Cancel pending/overdue installments — amounts were sized for the old formation
        $cancelled = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->whereIn('status', ['en_attente', 'en_retard'])
            ->count();

        Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->whereIn('status', ['en_attente', 'en_retard'])
            ->update(['status' => 'annule']);

        $msg = "{$learner->first_name} {$learner->last_name} déplacé vers « {$targetCohort->name} ».";
        if ($transferred > 0) {
            $msg .= " {$transferred} versement(s) transféré(s).";
        }
        if ($cancelled > 0) {
            $msg .= " {$cancelled} tranche(s) en attente annulée(s) (à replanifier dans la nouvelle cohorte).";
        }

        return back()->with('success', $msg);
    }
}
