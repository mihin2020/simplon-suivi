<?php

namespace App\Services\Dashboard;

use App\Enums\FormationStatus;
use App\Enums\InsertionStatus;
use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** The figures of the administrator's home page. */
class AdminDashboard
{
    private const LIST_SIZE = 5;

    /** @return array<string, mixed> */
    public function build(): array
    {
        // Learners who were tracked for insertion, with their latest known status (one entry per learner).
        $insertionByLearner = InsertionRecord::query()
            ->orderByDesc('status_changed_at')
            ->orderByDesc('created_at')
            ->get(['learner_id', 'status'])
            ->unique('learner_id');

        $tracked = $insertionByLearner->count();
        $inserted = $insertionByLearner->whereIn('status', [InsertionStatus::Employed, InsertionStatus::Internship])->count();

        return [
            'role' => 'admin',
            'stats' => [
                'projects' => [
                    'total' => Project::count(),
                    'active' => Project::whereHas('formations', fn ($query) => $query->where('status', FormationStatus::Active))->count(),
                ],
                'formations' => [
                    'total' => Formation::count(),
                    'active' => Formation::where('status', FormationStatus::Active)->count(),
                ],
                'learners' => [
                    'total' => Learner::count(),
                    'active' => Learner::whereHas('formations', fn ($query) => $query->where('formation_learner.status', LearnerStatus::InProgress->value))->count(),
                ],
                'trainers' => ['total' => Trainer::count()],
                'insertion_rate' => $tracked > 0 ? (int) round($inserted / $tracked * 100) : 0,
            ],
            'learnerStatus' => $this->learnerStatus(),
            'insertion' => collect(InsertionStatus::cases())->map(fn (InsertionStatus $status) => [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => $insertionByLearner->where('status', $status)->count(),
            ])->values(),
            'insertionTracked' => $tracked,
            'gender' => $this->gender(),
            'activeFormations' => $this->activeFormations(),
            'recentEnrollments' => $this->recentEnrollments(),
        ];
    }

    /** Distinct learners per enrolment status. */
    private function learnerStatus(): Collection
    {
        $totals = DB::table('formation_learner')
            ->join('learners', 'learners.id', '=', 'formation_learner.learner_id')
            ->whereNull('learners.deleted_at')
            // A grouped distinct count cannot be expressed without a raw expression; it takes no user input.
            ->select('formation_learner.status', DB::raw('count(distinct formation_learner.learner_id) as total'))
            ->groupBy('formation_learner.status')
            ->pluck('total', 'status');

        return collect(LearnerStatus::cases())->map(fn (LearnerStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($totals[$status->value] ?? 0),
        ])->values();
    }

    /** @return array{male: int, female: int} */
    private function gender(): array
    {
        $totals = Learner::query()
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return ['male' => (int) ($totals['male'] ?? 0), 'female' => (int) ($totals['female'] ?? 0)];
    }

    private function activeFormations()
    {
        return Formation::with('project')
            ->where('status', FormationStatus::Active)
            ->withCount(['learners as active_learners_count' => fn ($query) => $query->where('formation_learner.status', LearnerStatus::InProgress->value)])
            ->orderByDesc('started_at')
            ->paginate(self::LIST_SIZE)
            ->through(fn (Formation $formation) => [
                'id' => $formation->id,
                'name' => $formation->name,
                'project_name' => $formation->project->name,
                'active_learners_count' => $formation->active_learners_count,
                'started_at' => $formation->started_at->format('d/m/Y'),
                'ended_at' => $formation->ended_at?->format('d/m/Y'),
            ]);
    }

    /** The latest enrolments, of learners and formations that still exist. */
    private function recentEnrollments(): Collection
    {
        return DB::table('formation_learner')
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
            ->limit(self::LIST_SIZE)
            ->get();
    }
}
