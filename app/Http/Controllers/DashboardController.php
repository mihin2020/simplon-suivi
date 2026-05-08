<?php

namespace App\Http\Controllers;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        if ($user->role === UserRole::Trainer) {
            return $this->trainerDashboard($user);
        }

        return $this->adminDashboard();
    }

    private function adminDashboard(): Response
    {
        $activeFormations = Formation::with('project')
            ->where('status', FormationStatus::Active)
            ->withCount(['learners as active_learners_count' => fn ($q) =>
                $q->where('formation_learner.status', LearnerStatus::InProgress->value)
            ])
            ->orderByDesc('started_at')
            ->paginate(5)
            ->through(fn ($f) => [
                'id'                    => $f->id,
                'name'                  => $f->name,
                'project_name'          => $f->project->name,
                'active_learners_count' => $f->active_learners_count,
                'started_at'            => $f->started_at?->format('d/m/Y'),
                'ended_at'              => $f->ended_at?->format('d/m/Y'),
            ]);

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
            'role'  => 'admin',
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
                'trainers'       => ['total' => Trainer::count()],
                'insertion_rate' => 0,
            ],
            'activeFormations'  => $activeFormations,
            'recentEnrollments' => $recentEnrollments,
        ]);
    }

    private function trainerDashboard($user): Response
    {
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (! $trainer) {
            return Inertia::render('Dashboard/Index', [
                'role'           => 'trainer',
                'trainer'        => null,
                'myFormations'   => [],
                'trainerStats'   => ['formations' => 0, 'learners' => 0, 'attendances_today' => 0],
            ]);
        }

        $myFormations = $trainer->formations()
            ->with('project')
            ->withCount(['learners as active_learners_count' => fn ($q) =>
                $q->where('formation_learner.status', LearnerStatus::InProgress->value)
            ])
            ->withCount(['attendances as attendances_today_count' => fn ($q) =>
                $q->whereDate('date', today())
            ])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($f) => [
                'id'                       => $f->id,
                'name'                     => $f->name,
                'project_name'             => $f->project->name,
                'status'                   => $f->status->value,
                'active_learners_count'    => $f->active_learners_count,
                'attendances_today_count'  => $f->attendances_today_count,
                'started_at'               => $f->started_at?->format('d/m/Y'),
                'ended_at'                 => $f->ended_at?->format('d/m/Y'),
                'is_lead'                  => (bool) $f->pivot->is_lead,
            ]);

        $formationIds = $trainer->formations()->pluck('formations.id');

        $totalLearners = DB::table('formation_learner')
            ->whereIn('formation_id', $formationIds)
            ->where('status', LearnerStatus::InProgress->value)
            ->distinct('learner_id')
            ->count('learner_id');

        $attendancesToday = DB::table('attendances')
            ->whereIn('formation_id', $formationIds)
            ->whereDate('date', today())
            ->count();

        return Inertia::render('Dashboard/Index', [
            'role'    => 'trainer',
            'trainer' => [
                'id'         => $trainer->id,
                'full_name'  => $trainer->full_name,
                'speciality' => $trainer->speciality,
            ],
            'myFormations' => $myFormations,
            'trainerStats' => [
                'formations'       => $myFormations->count(),
                'learners'         => $totalLearners,
                'attendances_today' => $attendancesToday,
            ],
        ]);
    }
}
