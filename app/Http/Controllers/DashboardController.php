<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceCode;
use App\Enums\FormationStatus;
use App\Enums\InsertionStatus;
use App\Enums\LearnerStatus;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Project;
use App\Models\Trainer;
use App\Models\User;
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

        // ── Répartition des apprenants par statut (parcours) ──
        $statusRaw = DB::table('formation_learner')
            ->join('learners', 'learners.id', '=', 'formation_learner.learner_id')
            ->whereNull('learners.deleted_at')
            ->select('formation_learner.status', DB::raw('count(distinct formation_learner.learner_id) as total'))
            ->groupBy('formation_learner.status')
            ->pluck('total', 'status');

        $learnerStatus = collect(LearnerStatus::cases())->map(fn ($s) => [
            'key'   => $s->value,
            'label' => $s->label(),
            'count' => (int) ($statusRaw[$s->value] ?? 0),
        ])->values();

        // ── Insertion professionnelle (dernier statut connu par apprenant) ──
        $insertionByLearner = InsertionRecord::query()
            ->orderByDesc('status_changed_at')
            ->orderByDesc('created_at')
            ->get(['learner_id', 'status'])
            ->unique('learner_id');

        $insertion = collect(InsertionStatus::cases())->map(fn ($s) => [
            'key'   => $s->value,
            'label' => $s->label(),
            'count' => $insertionByLearner->where('status', $s)->count(),
        ])->values();

        $trackedInsertion = $insertionByLearner->count();
        $insertedCount    = $insertionByLearner->whereIn('status', [InsertionStatus::Employed, InsertionStatus::Internship])->count();
        $insertionRate    = $trackedInsertion > 0 ? (int) round($insertedCount / $trackedInsertion * 100) : 0;

        // ── Parité Hommes / Femmes ──
        $genderRaw = Learner::query()
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender');
        $male   = (int) ($genderRaw['male'] ?? 0);
        $female = (int) ($genderRaw['female'] ?? 0);

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
                'insertion_rate' => $insertionRate,
            ],
            'learnerStatus'      => $learnerStatus,
            'insertion'          => $insertion,
            'insertionTracked'   => $trackedInsertion,
            'gender'             => ['male' => $male, 'female' => $female],
            'activeFormations'   => $activeFormations,
            'recentEnrollments'  => $recentEnrollments,
        ]);
    }

    private function trainerDashboard(User $user): Response
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
            ->map(function ($f) {
                $daysUntilEnd = $f->ended_at ? today()->diffInDays($f->ended_at, false) : null;

                return [
                    'id'                       => $f->id,
                    'name'                     => $f->name,
                    'project_name'             => $f->project->name,
                    'status'                   => $f->status->value,
                    'active_learners_count'    => $f->active_learners_count,
                    'attendances_today_count'  => $f->attendances_today_count,
                    'needs_attendance_today'   => $f->status === FormationStatus::Active
                        && $f->active_learners_count > 0
                        && $f->attendances_today_count === 0,
                    'ending_soon'              => $f->status === FormationStatus::Active
                        && $daysUntilEnd !== null
                        && $daysUntilEnd >= 0
                        && $daysUntilEnd <= 30,
                    'started_at'               => $f->started_at?->format('d/m/Y'),
                    'ended_at'                 => $f->ended_at?->format('d/m/Y'),
                    'is_lead'                  => (bool) $f->pivot->is_lead,
                ];
            });

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

        // ── Apprenants avec absences répétées sur les 7 derniers jours ──
        $recentAbsences = DB::table('attendances')
            ->join('learners', 'learners.id', '=', 'attendances.learner_id')
            ->join('formations', 'formations.id', '=', 'attendances.formation_id')
            ->whereIn('attendances.formation_id', $formationIds)
            ->whereIn('attendances.code', [
                AttendanceCode::AbsentJustified->value,
                AttendanceCode::AbsentNotJustified->value,
            ])
            ->whereDate('attendances.date', '>=', today()->subDays(7))
            ->whereNull('learners.deleted_at')
            ->select(
                'learners.id',
                'learners.first_name',
                'learners.last_name',
                'formations.name as formation_name',
                DB::raw('count(*) as absences_count'),
            )
            ->groupBy('learners.id', 'learners.first_name', 'learners.last_name', 'formations.name')
            ->having('absences_count', '>=', 2)
            ->orderByDesc('absences_count')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'role'    => 'trainer',
            'trainer' => [
                'id'         => $trainer->id,
                'full_name'  => $trainer->full_name,
                'speciality' => $trainer->speciality,
            ],
            'myFormations'   => $myFormations,
            'recentAbsences' => $recentAbsences,
            'trainerStats'   => [
                'formations'       => $myFormations->count(),
                'learners'         => $totalLearners,
                'attendances_today' => $attendancesToday,
            ],
        ]);
    }
}
