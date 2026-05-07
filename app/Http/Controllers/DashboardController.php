<?php

namespace App\Http\Controllers;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Formations actives avec compte d'apprenants
        $activeFormations = Formation::with('project')
            ->where('status', FormationStatus::Active)
            ->withCount(['learners as active_learners_count' => fn ($q) =>
                $q->where('formation_learner.status', LearnerStatus::InProgress->value)
            ])
            ->orderByDesc('started_at')
            ->limit(6)
            ->get()
            ->map(fn ($f) => [
                'id'                   => $f->id,
                'name'                 => $f->name,
                'project_name'         => $f->project->name,
                'active_learners_count' => $f->active_learners_count,
                'started_at'           => $f->started_at?->format('d/m/Y'),
                'ended_at'             => $f->ended_at?->format('d/m/Y'),
            ]);

        // Apprenants récemment inscrits (via la table pivot)
        $recentEnrollments = DB::table('formation_learner')
            ->join('learners', 'learners.id', '=', 'formation_learner.learner_id')
            ->join('formations', 'formations.id', '=', 'formation_learner.formation_id')
            ->whereNull('learners.deleted_at')
            ->whereNull('formations.deleted_at')
            ->select(
                'learners.id',
                'learners.first_name',
                'learners.last_name',
                'learners.photo_path',
                'formations.name as formation_name',
                'formation_learner.enrolled_at',
            )
            ->orderByDesc('formation_learner.enrolled_at')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'projects'  => [
                    'total'  => Project::count(),
                    'active' => Project::whereHas('formations', fn ($q) =>
                        $q->where('status', FormationStatus::Active)
                    )->count(),
                ],
                'formations' => [
                    'total'  => Formation::count(),
                    'active' => Formation::where('status', FormationStatus::Active)->count(),
                ],
                'learners' => [
                    'total'  => Learner::count(),
                    'active' => Learner::whereHas('formations', fn ($q) =>
                        $q->where('formation_learner.status', LearnerStatus::InProgress->value)
                    )->count(),
                ],
                'trainers'        => ['total' => Trainer::count()],
                'insertion_rate'  => 0,
            ],
            'activeFormations'  => $activeFormations,
            'recentEnrollments' => $recentEnrollments,
        ]);
    }
}
