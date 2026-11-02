<?php

namespace App\Services;

use App\Enums\AttendanceCode;
use App\Enums\DayType;
use App\Enums\LearnerStatus;
use App\Enums\RecapView;
use App\Models\Formation;
use App\Models\Learner;
use App\Support\AttendancePeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    public function __construct(private WorkingDaysService $workingDays) {}

    /**
     * Active learners, sorted by family name then first name.
     *
     * @return Collection<int, Learner>
     */
    public function orderedLearners(Formation $formation): Collection
    {
        return $formation->activeLearners()
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get();
    }

    /**
     * Course days of a formation (Y-m-d), oldest first: every working day between the
     * start of the formation and today (or its end), plus any exceptional day already recorded.
     *
     * @return Collection<int, string>
     */
    public function courseDays(Formation $formation): Collection
    {
        $calendar = collect();

        if ($formation->started_at) {
            $today = now()->startOfDay();
            $end = $formation->ended_at && $formation->ended_at->lt($today) ? $formation->ended_at : $today;

            if ($formation->started_at->lte($end)) {
                $calendar = collect($this->workingDays->getWorkingDays($formation->started_at, $end))
                    ->map(fn (Carbon $day) => $day->toDateString());
            }
        }

        $recorded = $formation->attendances()
            ->toBase()
            ->distinct()
            ->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10));

        return $calendar->merge($recorded)->unique()->sort()->values();
    }

    public function dayType(Formation $formation, Carbon $date): DayType
    {
        if (! $this->workingDays->isWorkingDay($date)) {
            return DayType::Weekend;
        }

        return $this->isWithinFormation($formation, $date) ? DayType::Course : DayType::OutsidePeriod;
    }

    /** Weekends stay allowed (exceptional class); only dates outside the formation period are refused. */
    public function isWithinFormation(Formation $formation, Carbon $date): bool
    {
        return ! (($formation->started_at && $date->lt($formation->started_at))
            || ($formation->ended_at && $date->gt($formation->ended_at)));
    }

    /** Date from which the learner counts in the attendance sheets (null = since the start). */
    public function enrolledFrom(Learner $learner): ?string
    {
        $enrolledAt = $learner->pivot?->enrolled_at;

        return $enrolledAt ? Carbon::parse($enrolledAt)->toDateString() : null;
    }

    /**
     * Last day the learner counts in the sheets: null while in progress, false when they left
     * without a known date (only the days actually recorded then count).
     */
    public function enrolledUntil(Learner $learner): string|false|null
    {
        if ($learner->pivot?->status === LearnerStatus::InProgress->value) {
            return null;
        }

        $left = $learner->pivot?->withdrawn_at ?? $learner->pivot?->completed_at;

        return $left ? Carbon::parse($left)->toDateString() : false;
    }

    /**
     * Learners of the recap: those in progress plus those who left but have days recorded in the
     * period, so the attendance history stays visible.
     *
     * @return Collection<int, Learner>
     */
    private function recapLearners(Formation $formation, Carbon $from, Carbon $to): Collection
    {
        return $formation->learners()
            ->where(function ($query) use ($formation, $from, $to) {
                $query->where('formation_learner.status', LearnerStatus::InProgress->value)
                    ->orWhereIn('learners.id', $formation->attendances()->toBase()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->select('learner_id'));
            })
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get();
    }

    /**
     * Rows of the daily sheet. Learners enrolled after the date are flagged, not marked absent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dayRows(Formation $formation, Carbon $date): array
    {
        $day = $date->toDateString();

        $attendances = $formation->attendances()
            ->whereDate('date', $day)
            ->get()
            ->keyBy('learner_id');

        return $this->orderedLearners($formation)->map(function (Learner $learner) use ($attendances, $day) {
            $attendance = $attendances->get($learner->id);
            $enrolledFrom = $this->enrolledFrom($learner);

            return [
                'id' => $learner->id,
                'name' => $learner->display_name,
                'enrolled_from' => $enrolledFrom,
                'joined_today' => $enrolledFrom === $day,
                'not_enrolled_yet' => $enrolledFrom !== null && $enrolledFrom > $day && $attendance === null,
                'attendance' => $attendance
                    ? ['code' => $attendance->code->value, 'comment' => $attendance->comment]
                    : null,
            ];
        })->values()->all();
    }

    /**
     * Completion of every course day, to spot at a glance the days that were not filled in.
     *
     * @param  Collection<int, string>  $courseDays
     * @return array<int, array{date: string, state: string}>
     */
    public function dayStrip(Formation $formation, Collection $courseDays): array
    {
        $learners = $this->orderedLearners($formation);

        // An aggregate cannot be expressed without a raw select; it takes no user input.
        $recordedPerDay = $formation->attendances()
            ->toBase()
            ->whereIn('learner_id', $learners->modelKeys())
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [substr((string) $date, 0, 10) => (int) $total]);

        $enrolledFrom = $learners->map(fn (Learner $learner) => $this->enrolledFrom($learner));

        return $courseDays->map(function (string $day) use ($recordedPerDay, $enrolledFrom) {
            $expected = $enrolledFrom->filter(fn (?string $from) => $from === null || $from <= $day)->count();
            $recorded = $recordedPerDay->get($day, 0);

            $state = match (true) {
                $recorded === 0 => 'empty',
                $recorded >= $expected => 'complete',
                default => 'partial',
            };

            return ['date' => $day, 'state' => $state];
        })->all();
    }

    /** Null when the formation has no course day yet. */
    public function period(Formation $formation, RecapView $view, ?string $anchor, ?Collection $courseDays = null): ?AttendancePeriod
    {
        $days = $courseDays ?? $this->courseDays($formation);

        if ($days->isEmpty()) {
            return null;
        }

        return AttendancePeriod::resolve($view, $anchor, Carbon::parse($days->first()), Carbon::parse($days->last()));
    }

    /**
     * Recap of a period: per-learner cells and totals, per-day stats and the weekly layout.
     *
     * @return array<string, mixed>
     */
    public function recap(Formation $formation, Carbon $from, Carbon $to, ?Collection $courseDays = null): array
    {
        $days = ($courseDays ?? $this->courseDays($formation))
            ->filter(fn (string $day) => $day >= $from->toDateString() && $day <= $to->toDateString())
            ->values();

        $learners = $this->recapLearners($formation, $from, $to);

        $attendances = $formation->attendances()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('learner_id')
            ->map(fn (Collection $items) => $items->keyBy(fn ($a) => $a->date->toDateString()));

        $dayStats = $days->mapWithKeys(fn (string $day) => [$day => ['present' => 0, 'expected' => 0]])->all();

        $rows = $learners->map(function (Learner $learner) use ($days, $attendances, &$dayStats) {
            $enrolledFrom = $this->enrolledFrom($learner);
            $enrolledUntil = $this->enrolledUntil($learner);
            $own = $attendances->get($learner->id, collect());
            $counts = array_fill_keys(array_column(AttendanceCode::cases(), 'value'), 0);
            $cells = [];
            $expected = 0;
            $recorded = 0;
            $present = 0;

            foreach ($days as $day) {
                $attendance = $own->get($day);
                $enrolled = $attendance !== null
                    || (($enrolledFrom === null || $enrolledFrom <= $day) && ($enrolledUntil === false || $enrolledUntil === null || $day <= $enrolledUntil));

                $cells[$day] = [
                    'enrolled' => $enrolled,
                    'code' => $attendance?->code->value,
                    'comment' => $attendance?->comment,
                ];

                if (! $enrolled) {
                    continue;
                }

                $expected++;
                $dayStats[$day]['expected']++;

                if ($attendance) {
                    $recorded++;
                    $counts[$attendance->code->value]++;

                    if ($attendance->code->countsAsPresent()) {
                        $present++;
                        $dayStats[$day]['present']++;
                    }
                }
            }

            return [
                'id' => $learner->id,
                'name' => $learner->display_name,
                'days' => $cells,
                'counts' => $counts,
                'absences' => $counts[AttendanceCode::AbsentJustified->value] + $counts[AttendanceCode::AbsentNotJustified->value],
                'expected' => $expected,
                'recorded' => $recorded,
                'rate' => $recorded > 0 ? (int) round($present / $recorded * 100) : null,
            ];
        })->values()->all();

        return [
            'days' => $days->all(),
            'weeks' => $this->weeks($formation, $days),
            'rows' => $rows,
            'dayStats' => $dayStats,
            'reasons' => $this->reasons($rows),
        ];
    }

    /**
     * Group course days by ISO week, numbered from the first week of the formation.
     *
     * @param  Collection<int, string>  $days
     * @return array<int, array{number: int|null, start: string, end: string, days: array<int, string>}>
     */
    private function weeks(Formation $formation, Collection $days): array
    {
        $firstWeek = $formation->started_at?->copy()->startOfWeek(Carbon::MONDAY);

        return $days
            ->groupBy(fn (string $day) => Carbon::parse($day)->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(function (Collection $weekDays, string $monday) use ($firstWeek) {
                $start = Carbon::parse($monday);

                return [
                    'number' => $firstWeek ? (int) $firstWeek->diffInWeeks($start, false) + 1 : null,
                    'start' => $start->toDateString(),
                    'end' => $start->copy()->addDays(4)->toDateString(),
                    'days' => $weekDays->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Every recorded reason (justified absences / lateness) of the recap.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{learner: string, date: string, code: string, reason: string}>
     */
    private function reasons(array $rows): array
    {
        $reasons = [];

        foreach ($rows as $row) {
            foreach ($row['days'] as $day => $cell) {
                if (! empty($cell['comment'])) {
                    $reasons[] = [
                        'learner' => $row['name'],
                        'date' => $day,
                        'code' => $cell['code'],
                        'reason' => $cell['comment'],
                    ];
                }
            }
        }

        usort($reasons, fn ($a, $b) => [$a['date'], $a['learner']] <=> [$b['date'], $b['learner']]);

        return $reasons;
    }

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

        $total = $attendances->count();
        $present = $attendances->filter(fn ($a) => $a->code === AttendanceCode::Present)->count();
        $absentJustified = $attendances->filter(fn ($a) => $a->code === AttendanceCode::AbsentJustified)->count();
        $absentNotJustified = $attendances->filter(fn ($a) => $a->code === AttendanceCode::AbsentNotJustified)->count();
        $late = $attendances->filter(fn ($a) => in_array($a->code, [AttendanceCode::LateJustified, AttendanceCode::LateNotJustified]))->count();

        return [
            'total' => $total,
            'present' => $present,
            'absent_justified' => $absentJustified,
            'absent_not_justified' => $absentNotJustified,
            'late' => $late,
            'rate' => $total > 0 ? round(($present + $late) / $total * 100, 1) : 0.0,
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
