<?php

namespace App\Services;

use App\Enums\AttendanceCode;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function __construct(private WorkingDaysService $workingDays) {}

    /**
     * Attendance stats for a learner in a formation.
     *
     * @return array{total: int, present: int, absent_justified: int, absent_not_justified: int, late: int, rate: float}
     */
    public function statsForLearner(Formation $formation, Learner $learner): array
    {
        $attendances = $formation->attendances()
            ->where('learner_id', $learner->id)
            ->get();

        $total               = $attendances->count();
        $present             = $attendances->filter(fn ($a) => $a->code === AttendanceCode::Present)->count();
        $absentJustified     = $attendances->filter(fn ($a) => $a->code === AttendanceCode::AbsentJustified)->count();
        $absentNotJustified  = $attendances->filter(fn ($a) => $a->code === AttendanceCode::AbsentNotJustified)->count();
        $late                = $attendances->filter(fn ($a) => in_array($a->code, [AttendanceCode::LateJustified, AttendanceCode::LateNotJustified]))->count();

        return [
            'total'                => $total,
            'present'              => $present,
            'absent_justified'     => $absentJustified,
            'absent_not_justified' => $absentNotJustified,
            'late'                 => $late,
            'rate'                 => $total > 0 ? round(($present + $late) / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Attendance grid for a formation on a given date range.
     * Returns [learner_id => [date => AttendanceCode|null]].
     *
     * @return array<string, array<string, AttendanceCode|null>>
     */
    public function gridForFormation(Formation $formation, Carbon $start, Carbon $end): array
    {
        $attendances = $formation->attendances()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('learner_id');

        $grid = [];

        foreach ($formation->activeLearners()->get() as $learner) {
            $grid[$learner->id] = [];
            $learnerAttendances = $attendances->get($learner->id, collect());

            foreach ($learnerAttendances as $attendance) {
                $grid[$learner->id][$attendance->date->toDateString()] = $attendance->code;
            }
        }

        return $grid;
    }

    /**
     * Global attendance rate for a formation (all learners combined).
     */
    public function rateForFormation(Formation $formation): float
    {
        $total = $formation->attendances()->count();

        if ($total === 0) {
            return 0.0;
        }

        $present = $formation->attendances()
            ->whereIn('code', [
                AttendanceCode::Present->value,
                AttendanceCode::LateJustified->value,
                AttendanceCode::LateNotJustified->value,
            ])
            ->count();

        return round($present / $total * 100, 1);
    }
}
