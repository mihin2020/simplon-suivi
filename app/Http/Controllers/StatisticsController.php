<?php

namespace App\Http\Controllers;

use App\Exports\FormationStatisticsSheet;
use App\Exports\ProjectStatisticsExport;
use App\Http\Requests\Statistics\FormationLearnersFilterRequest;
use App\Models\Formation;
use App\Models\Project;
use App\Services\Statistics\FormationLearnerFinder;
use App\Services\Statistics\StatisticsReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StatisticsController extends Controller
{
    public function index(StatisticsReport $report): Response
    {
        return Inertia::render('Statistics/Index', $report->build());
    }

    /**
     * API: les apprenants d'une formation, avec filtres optionnels (genre, statut, insertion).
     */
    public function learners(FormationLearnersFilterRequest $request, Formation $formation, FormationLearnerFinder $finder): JsonResponse
    {
        $learners = $finder->find($formation, $request->validated());

        return response()->json([
            'formation' => [
                'id' => $formation->id,
                'name' => $formation->name,
            ],
            'count' => count($learners),
            'learners' => $learners,
        ]);
    }

    /**
     * Exporte les statistiques d'un projet : un classeur xlsx avec une feuille
     * récapitulative + une feuille par formation.
     */
    public function exportProject(Project $project): BinaryFileResponse
    {
        $filename = 'statistiques-'.Str::slug($project->name).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ProjectStatisticsExport($project), $filename);
    }

    /**
     * Exporte les statistiques d'une seule formation (résumé + liste nominative).
     */
    public function exportFormation(Formation $formation): BinaryFileResponse
    {
        $filename = 'statistiques-'.Str::slug($formation->name).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new FormationStatisticsSheet($formation), $filename);
    }
}
