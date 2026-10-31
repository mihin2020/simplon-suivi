<?php

namespace App\Support;

use App\Enums\RecapView;
use Illuminate\Support\Carbon;

/**
 * A navigable slice (week, month or whole formation) of the attendance calendar.
 */
final class AttendancePeriod
{
    private function __construct(
        public readonly RecapView $view,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $label,
        public readonly ?string $prevAnchor,
        public readonly ?string $nextAnchor,
    ) {}

    /**
     * @param  Carbon  $first  First course day of the formation
     * @param  Carbon  $last  Last course day of the formation
     */
    public static function resolve(RecapView $view, ?string $anchor, Carbon $first, Carbon $last): self
    {
        $reference = $anchor ? Carbon::parse($anchor) : now();
        $reference = $reference->lt($first) ? $first->copy() : ($reference->gt($last) ? $last->copy() : $reference);
        $reference = $reference->startOfDay();

        if ($view === RecapView::All) {
            return new self($view, $first->copy(), $last->copy(), 'Toute la formation', null, null);
        }

        if ($view === RecapView::Week) {
            $from = $reference->copy()->startOfWeek(Carbon::MONDAY);
            $to = $from->copy()->addDays(4);
            $label = 'Semaine du '.$from->locale('fr')->isoFormat('D MMM').' au '.$to->locale('fr')->isoFormat('D MMM YYYY');
            $prev = $from->copy()->subWeek();
            $next = $from->copy()->addWeek();
        } else {
            $from = $reference->copy()->startOfMonth();
            $to = $reference->copy()->endOfMonth()->startOfDay();
            $label = ucfirst($from->locale('fr')->isoFormat('MMMM YYYY'));
            $prev = $from->copy()->subMonth();
            $next = $from->copy()->addMonth();
        }

        return new self(
            $view,
            $from,
            $to,
            $label,
            $from->gt($first) ? $prev->toDateString() : null,
            $to->lt($last) ? $next->toDateString() : null,
        );
    }
}
