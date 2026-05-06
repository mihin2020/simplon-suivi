<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class WorkingDaysService
{
    /** Days of the week considered non-working (0=Sunday, 6=Saturday). */
    private array $weekendDays = [Carbon::SATURDAY, Carbon::SUNDAY];

    /**
     * Count working days between two dates (inclusive), excluding weekends and public holidays.
     *
     * @param  string[]  $holidays  Dates in 'Y-m-d' format to exclude.
     */
    public function countBetween(Carbon $start, Carbon $end, array $holidays = []): int
    {
        $count = 0;
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($current->lte($endDay)) {
            if ($this->isWorkingDay($current, $holidays)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Return all working days between two dates as an array of Carbon instances.
     *
     * @param  string[]  $holidays
     * @return Carbon[]
     */
    public function getWorkingDays(Carbon $start, Carbon $end, array $holidays = []): array
    {
        $days = [];
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($current->lte($endDay)) {
            if ($this->isWorkingDay($current, $holidays)) {
                $days[] = $current->copy();
            }
            $current->addDay();
        }

        return $days;
    }

    public function isWorkingDay(Carbon $date, array $holidays = []): bool
    {
        if (in_array($date->dayOfWeek, $this->weekendDays)) {
            return false;
        }

        return ! in_array($date->toDateString(), $holidays);
    }
}
