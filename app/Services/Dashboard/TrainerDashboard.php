<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceCode;
use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** The figures of a trainer's home page: their formations and what they still have to enter today. */
class TrainerDashboard
{
    /** A formation ending within this many days is flagged. */
    private const ENDING_SOON_DAYS = 30;

    /** At least this many absences within the window put a learner on the watch list. */
    private const REPEATED_ABSENCES = 2;

    private const ABSENCE_WINDOW_DAYS = 7;

    private const WATCH_LIST_SIZE = 5;

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $trainer = Trainer::where('user_id', $user->id)->first();

        if (! $trainer) {
            return [
                'role' => 'trainer',
                'trainer' => null,
                'myFormations' => [],
                'trainerStats' => ['formations' => 0, 'learners' => 0, 'attendances_today' => 0],
            ];
        }

        $trainer->load('profiles');
        $myFormations = $this->formations($trainer);
        $formationIds = $trainer->formations()->pluck('formations.id');

        return [
            'role' => 'trainer',
            'trainer' => [
                'id' => $trainer->id,
                'full_name' => $trainer->full_name,
                // Profiles replaced the old free-text speciality column.
                'speciality' => $trainer->profiles->pluck('name')->join(', ') ?: null,
            ],
            'myFormations' => $myFormations,
            'recentAbsences' => $this->repeatedAbsences($formationIds),
            'trainerStats' => [
                'formations' => $myFormations->count(),
                'learners' => DB::table('formation_learner')
                    ->whereIn('formation_id', $formationIds)
                    ->where('status', LearnerStatus::InProgress->value)
                    ->distinct('learner_id')
                    ->count('learner_id'),
                'attendances_today' => DB::table('attendances')
                    ->whereIn('formation_id', $formationIds)
                    ->whereDate('date', today())
                    ->count(),
            ],
        ];
    }

    private function formations(Trainer $trainer): Collection
    {
        return $trainer->formations()
            ->with('project')
            ->withCount(['learners as active_learners_count' => fn ($query) => $query->where('formation_learner.status', LearnerStatus::InProgress->value)])
            ->withCount(['attendances as attendances_today_count' => fn ($query) => $query->whereDate('date', today())])
            ->orderByDesc('started_at')
            ->get()
            ->map(function (Formation $formation) {
                $isActive = $formation->status === FormationStatus::Active;
                $daysUntilEnd = $formation->ended_at ? today()->diffInDays($formation->ended_at, false) : null;

                return [
                    'id' => $formation->id,
                    'name' => $formation->name,
                    'project_name' => $formation->project->name,
                    'status' => $formation->status->value,
                    'active_learners_count' => $formation->active_learners_count,
                    'attendances_today_count' => $formation->attendances_today_count,
                    // An active formation with learners and no sheet yet today.
                    'needs_attendance_today' => $isActive && $formation->active_learners_count > 0 && $formation->attendances_today_count === 0,
                    'ending_soon' => $isActive && $daysUntilEnd !== null && $daysUntilEnd >= 0 && $daysUntilEnd <= self::ENDING_SOON_DAYS,
                    'started_at' => $formation->started_at->format('d/m/Y'),
                    'ended_at' => $formation->ended_at?->format('d/m/Y'),
                    'is_lead' => (bool) $formation->getRelation('pivot')->is_lead,
                ];
            });
    }

    /**
     * Learners absent several times over the last days, in the trainer's formations.
     *
     * @param  Collection<int, string>  $formationIds
     */
    private function repeatedAbsences(Collection $formationIds): Collection
    {
        return DB::table('attendances')
            ->join('learners', 'learners.id', '=', 'attendances.learner_id')
            ->join('formations', 'formations.id', '=', 'attendances.formation_id')
            ->whereIn('attendances.formation_id', $formationIds)
            ->whereIn('attendances.code', [
                AttendanceCode::AbsentJustified->value,
                AttendanceCode::AbsentNotJustified->value,
            ])
            ->whereDate('attendances.date', '>=', today()->subDays(self::ABSENCE_WINDOW_DAYS))
            ->whereNull('learners.deleted_at')
            // A grouped count cannot be expressed without a raw expression; it takes no user input.
            ->select(
                'learners.id',
                'learners.first_name',
                'learners.last_name',
                'formations.name as formation_name',
                DB::raw('count(*) as absences_count'),
            )
            ->groupBy('learners.id', 'learners.first_name', 'learners.last_name', 'formations.name')
            ->having('absences_count', '>=', self::REPEATED_ABSENCES)
            ->orderByDesc('absences_count')
            ->limit(self::WATCH_LIST_SIZE)
            ->get();
    }
}
