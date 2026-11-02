<?php

namespace App\Services\Campus;

use App\Enums\PaymentStatus;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Support\Collection;

/** The campus finance page: what is expected, collected and late, per formation and per cohort, for one year. */
class FinanceOverview
{
    private const OVERDUE_LIST_SIZE = 10;

    /** Years in which at least one cohort started, newest first (the current year when there is none). */
    public function years(): Collection
    {
        // The year is read in PHP: YEAR() only exists in MySQL, and there are only a few cohorts.
        $years = Cohort::query()->get(['started_at'])
            ->map(fn (Cohort $cohort) => $cohort->started_at->year)
            ->unique()
            ->sortDesc()
            ->values();

        return $years->isEmpty() ? collect([now()->year]) : $years;
    }

    /**
     * @return array{byFormation: Collection<int, array<string, mixed>>, globalStats: array<string, mixed>, recentOverdue: Collection<int, array<string, mixed>>}
     */
    public function forYear(int $year): array
    {
        $cohorts = Cohort::with([
            'campusFormation' => fn ($query) => $query->withTrashed(),
            'payments',
            'activeLearners' => fn ($query) => $query->select('learners.id'),
        ])
            ->withCount('learners')
            ->whereYear('started_at', $year)
            ->orderBy('started_at')
            ->get();

        $cohortIds = $cohorts->pluck('id')->all();
        $stats = $cohorts->map(fn (Cohort $cohort) => $this->cohortStats($cohort));

        return [
            'byFormation' => $this->groupByFormation($stats),
            'globalStats' => $this->globalStats($stats, $cohortIds),
            'recentOverdue' => $this->recentOverdue($cohortIds),
        ];
    }

    /** @return array<string, mixed> */
    private function cohortStats(Cohort $cohort): array
    {
        $unitCost = $cohort->campusFormation->total_cost ?? 0;
        $activeCount = $cohort->activeLearners->count();

        // What should come in: the cost of the formation for each active learner.
        $expected = $unitCost * $activeCount;
        $collected = $cohort->payments->where('status', PaymentStatus::Paye)->sum('amount');

        // Distinct learners with a late installment: flagged late, or still pending and due date reached.
        $overdueCount = $cohort->payments
            ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::EnRetard
                || ($payment->status === PaymentStatus::EnAttente && $payment->due_date->isPast()))
            ->pluck('learner_id')
            ->unique()
            ->count();

        return [
            'id' => $cohort->id,
            'name' => $cohort->name,
            'started_at' => $cohort->started_at->format('Y-m-d'),
            'ended_at' => $cohort->ended_at->format('Y-m-d'),
            'status' => $cohort->status->value,
            'learners_count' => $cohort->learners_count,
            'active_count' => $activeCount,
            'unit_cost' => $unitCost,
            'total_expected' => $expected,
            'total_collected' => $collected,
            'total_remaining' => max(0, $expected - $collected),
            'overdue_count' => $overdueCount,
            'collect_rate' => $this->rate($collected, $expected),
            'formation' => [
                'id' => $cohort->campusFormation->id ?? $cohort->campus_formation_id,
                'name' => $cohort->campusFormation->name ?? '(Formation supprimée)',
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $stats
     */
    private function groupByFormation(Collection $stats): Collection
    {
        return $stats
            ->groupBy(fn (array $cohort) => $cohort['formation']['id'])
            ->map(function (Collection $cohorts) {
                $first = $cohorts->first();
                $expected = $cohorts->sum('total_expected');
                $collected = $cohorts->sum('total_collected');

                return [
                    'id' => $first['formation']['id'],
                    'name' => $first['formation']['name'],
                    'cohorts' => $cohorts->values()->all(),
                    'totals' => [
                        'total_expected' => $expected,
                        'total_collected' => $collected,
                        'total_remaining' => $cohorts->sum('total_remaining'),
                        'overdue_count' => $cohorts->sum('overdue_count'),
                        'collect_rate' => $this->rate($collected, $expected),
                    ],
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $stats
     * @param  array<int, string>  $cohortIds
     * @return array<string, mixed>
     */
    private function globalStats(Collection $stats, array $cohortIds): array
    {
        $expected = $stats->sum('total_expected');
        $collected = Payment::whereIn('cohort_id', $cohortIds)->where('status', PaymentStatus::Paye)->sum('amount');

        return [
            'total_expected' => $expected,
            'total_collected' => $collected,
            'total_remaining' => max(0, $expected - $collected),
            // Only pending installments strictly past due (the "late" status is not counted here).
            'overdue_count' => Payment::overdue()->whereIn('cohort_id', $cohortIds)->distinct('learner_id')->count('learner_id'),
            'paid_this_month' => Payment::paid()
                ->whereIn('cohort_id', $cohortIds)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];
    }

    /**
     * @param  array<int, string>  $cohortIds
     */
    private function recentOverdue(array $cohortIds): Collection
    {
        $payments = Payment::overdue()
            ->whereIn('cohort_id', $cohortIds)
            ->with(['learner', 'cohort'])
            ->orderBy('due_date')
            ->limit(self::OVERDUE_LIST_SIZE)
            ->get();

        // One query for the names, deleted formations included.
        $formationNames = CampusFormation::withTrashed()
            ->whereIn('id', $payments->pluck('cohort.campus_formation_id')->unique())
            ->pluck('name', 'id');

        return $payments->map(fn (Payment $payment) => [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'due_date' => $payment->due_date->format('Y-m-d'),
            'learner' => [
                'id' => $payment->learner->id,
                'first_name' => $payment->learner->first_name,
                'last_name' => $payment->learner->last_name,
            ],
            'cohort' => [
                'id' => $payment->cohort->id,
                'name' => $payment->cohort->name,
                'campus_formation' => ['name' => $formationNames[$payment->cohort->campus_formation_id] ?? '(Formation supprimée)'],
            ],
        ]);
    }

    private function rate(int|float $collected, int|float $expected): int
    {
        return $expected > 0 ? min(100, (int) round($collected / $expected * 100)) : 0;
    }
}
