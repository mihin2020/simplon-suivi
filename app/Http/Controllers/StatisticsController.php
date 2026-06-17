<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\InsertionStatus;
use App\Enums\LearnerStatus;
use App\Exports\FormationStatisticsSheet;
use App\Exports\ProjectStatisticsExport;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class StatisticsController extends Controller
{
    public function index(): Response
    {
        // ========== STATS GLOBALES APPRENANTS ==========
        $totalLearners = Learner::count();
        $maleCount = Learner::where('gender', Gender::Male->value)->count();
        $femaleCount = Learner::where('gender', Gender::Female->value)->count();

        $learnersByStatus = DB::table('formation_learner')
            ->select('status', DB::raw('COUNT(DISTINCT learner_id) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // ========== DERNIER STATUT D'INSERTION PAR APPRENANT (pré-calculé une seule fois) ==========
        $latestInsertionMap = $this->latestInsertionRecordsByLearner();

        $insertionByStatus = collect($latestInsertionMap)->countBy();

        $employedCount   = $insertionByStatus->get(InsertionStatus::Employed->value, 0);
        $internshipCount = $insertionByStatus->get(InsertionStatus::Internship->value, 0);
        $unemployedCount = $totalLearners - $employedCount - $internshipCount;
        $insertionRate   = $totalLearners > 0
            ? round((($employedCount + $internshipCount) / $totalLearners) * 100, 1)
            : 0;

        // ========== STATS PAR PROJET / FORMATION ==========
        $projects = Project::with(['formations' => function ($q) {
            $q->withCount([
                'learners as total_learners',
                'learners as male_count' => fn ($q) => $q->where('learners.gender', Gender::Male->value),
                'learners as female_count' => fn ($q) => $q->where('learners.gender', Gender::Female->value),
                'activeLearners as in_progress_count',
                'withdrawnLearners as withdrawn_count',
                'completedLearners as completed_count',
                'movedLearners as moved_count',
            ])
            ->with(['learners:id']);
        }])
        ->orderBy('name')
        ->get();

        $projectStats = $projects->map(function ($project) use ($latestInsertionMap) {
            $formationStats = $project->formations->map(function ($formation) use ($latestInsertionMap) {
                $learnerIds = $formation->learners->pluck('id')->all();

                $internshipCount = 0;
                $employedCount   = 0;

                foreach ($learnerIds as $lid) {
                    $status = $latestInsertionMap[$lid] ?? null;
                    if ($status === InsertionStatus::Internship->value) $internshipCount++;
                    if ($status === InsertionStatus::Employed->value)   $employedCount++;
                }

                // "Sans emploi" = tout apprenant qui n'est ni en stage ni en emploi
                $unemployedCount = $formation->total_learners - $internshipCount - $employedCount;

                return [
                    'id' => $formation->id,
                    'name' => $formation->name,
                    'status' => $formation->status->value,
                    'total_learners' => $formation->total_learners,
                    'male_count' => $formation->male_count,
                    'female_count' => $formation->female_count,
                    'in_progress_count' => $formation->in_progress_count,
                    'withdrawn_count' => $formation->withdrawn_count,
                    'completed_count' => $formation->completed_count,
                    'moved_count' => $formation->moved_count,
                    'internship_count' => $internshipCount,
                    'employed_count' => $employedCount,
                    'unemployed_count' => $unemployedCount,
                ];
            });

            return [
                'id' => $project->id,
                'name' => $project->name,
                'formations' => $formationStats,
                'totals' => [
                    'total_learners' => $formationStats->sum('total_learners'),
                    'male_count' => $formationStats->sum('male_count'),
                    'female_count' => $formationStats->sum('female_count'),
                    'in_progress_count' => $formationStats->sum('in_progress_count'),
                    'withdrawn_count' => $formationStats->sum('withdrawn_count'),
                    'completed_count' => $formationStats->sum('completed_count'),
                    'moved_count' => $formationStats->sum('moved_count'),
                    'internship_count' => $formationStats->sum('internship_count'),
                    'employed_count' => $formationStats->sum('employed_count'),
                    'unemployed_count' => $formationStats->sum('unemployed_count'),
                ],
            ];
        });

        return Inertia::render('Statistics/Index', [
            'globalStats' => [
                'learners' => [
                    'total' => $totalLearners,
                    'male' => $maleCount,
                    'female' => $femaleCount,
                    'by_status' => [
                        'in_progress' => (int) ($learnersByStatus->get(LearnerStatus::InProgress->value, 0)),
                        'withdrawn' => (int) ($learnersByStatus->get(LearnerStatus::Withdrawn->value, 0)),
                        'completed' => (int) ($learnersByStatus->get(LearnerStatus::Completed->value, 0)),
                        'moved' => (int) ($learnersByStatus->get(LearnerStatus::Moved->value, 0)),
                    ],
                ],
                'insertion' => [
                    'internship' => $internshipCount,
                    'employed' => $employedCount,
                    'unemployed' => $unemployedCount,
                    'rate' => $insertionRate,
                ],
            ],
            'projectStats' => $projectStats,
        ]);
    }

    /**
     * API: Retourne les apprenants d'une formation avec filtres optionnels.
     */
    public function learners(\Illuminate\Http\Request $request, Formation $formation): \Illuminate\Http\JsonResponse
    {
        $query = $formation->learners();

        // Filtre par genre
        if ($request->filled('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        // Filtre par statut de formation
        if ($request->filled('status')) {
            $query->wherePivot('status', $request->input('status'));
        }

        // Filtre par statut d'insertion
        if ($request->filled('insertion_status')) {
            $insertionStatus = $request->input('insertion_status');
            $latestMap = $this->latestInsertionRecordsByLearner();

            if ($insertionStatus === 'unemployed') {
                // Sans emploi = ni en stage ni en emploi
                $placedIds = collect($latestMap)
                    ->filter(fn ($s) => in_array($s, [InsertionStatus::Internship->value, InsertionStatus::Employed->value]))
                    ->keys()
                    ->all();
                $query->whereNotIn('learners.id', $placedIds);
            } else {
                $learnerIds = collect($latestMap)
                    ->filter(fn ($s) => $s === $insertionStatus)
                    ->keys()
                    ->all();
                $query->whereIn('learners.id', $learnerIds);
            }
        }

        $learners = $query
            ->with('educationLevel')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['learners.id', 'first_name', 'last_name', 'email', 'gender', 'education_level_id'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'first_name' => $l->first_name,
                'last_name' => $l->last_name,
                'email' => $l->email,
                'gender' => $l->gender?->value,
                'education_level' => $l->educationLevel?->name,
                'status' => $l->pivot->status,
            ]);

        return response()->json([
            'formation' => [
                'id' => $formation->id,
                'name' => $formation->name,
            ],
            'count' => $learners->count(),
            'learners' => $learners,
        ]);
    }

    /**
     * Exporte les statistiques d'un projet : un classeur xlsx avec une feuille
     * récapitulative + une feuille par formation.
     */
    public function exportProject(Project $project): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = 'statistiques-' . Str::slug($project->name) . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new ProjectStatisticsExport($project), $filename);
    }

    /**
     * Exporte les statistiques d'une seule formation (résumé + liste nominative).
     */
    public function exportFormation(Formation $formation): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = 'statistiques-' . Str::slug($formation->name) . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new FormationStatisticsSheet($formation), $filename);
    }

    /**
     * Retourne un tableau associatif [learner_id => dernier_status_insertion].
     */
    private function latestInsertionRecordsByLearner(): array
    {
        $sub = DB::table('insertion_records')
            ->select('learner_id', DB::raw('MAX(status_changed_at) as max_date'))
            ->groupBy('learner_id');

        $rows = DB::table('insertion_records as ir')
            ->select('ir.learner_id', 'ir.status')
            ->joinSub($sub, 'latest', function ($join) {
                $join->on('ir.learner_id', '=', 'latest.learner_id')
                    ->on('ir.status_changed_at', '=', 'latest.max_date');
            })
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->learner_id] = $row->status;
        }

        return $map;
    }
}
