<?php

namespace App\Http\Controllers\Campus;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampusFinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', now()->year);

        $years = Cohort::selectRaw('YEAR(started_at) as year')
            ->groupBy('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y);

        if ($years->isEmpty()) {
            $years = collect([now()->year]);
        }

        $cohorts = Cohort::with([
                'campusFormation'  => fn ($q) => $q->withTrashed(),
                'payments',
                'activeLearners'   => fn ($q) => $q->select('learners.id'),
            ])
            ->withCount('learners')
            ->whereYear('started_at', $year)
            ->orderBy('started_at')
            ->get();

        $yearCohortIds = $cohorts->pluck('id')->all();

        $cohortStats = $cohorts->map(function (Cohort $cohort) {
            $unitCost    = $cohort->campusFormation?->total_cost ?? 0;
            $activeCount = $cohort->activeLearners->count();

            // Vérité métier : ce qui devrait entrer = coût × apprenants actifs
            $expected  = $unitCost * $activeCount;
            $collected = $cohort->payments
                ->where('status', PaymentStatus::Paye)
                ->sum('amount');
            $remaining = max(0, $expected - $collected);

            // Retards : apprenants distincts ayant au moins une échéance en retard
            $overdueCount = $cohort->payments
                ->filter(fn ($p) =>
                    $p->status === PaymentStatus::EnRetard
                    || ($p->status === PaymentStatus::EnAttente && $p->due_date?->isPast())
                )
                ->pluck('learner_id')
                ->unique()
                ->count();

            return [
                'id'              => $cohort->id,
                'name'            => $cohort->name,
                'started_at'      => $cohort->started_at->format('Y-m-d'),
                'ended_at'        => $cohort->ended_at->format('Y-m-d'),
                'status'          => $cohort->status->value,
                'learners_count'  => $cohort->learners_count,
                'active_count'    => $activeCount,
                'unit_cost'       => $unitCost,
                'total_expected'  => $expected,
                'total_collected' => $collected,
                'total_remaining' => $remaining,
                'overdue_count'   => $overdueCount,
                'collect_rate'    => $expected > 0 ? min(100, (int) round($collected / $expected * 100)) : 0,
                'formation'       => [
                    'id'   => $cohort->campusFormation?->id   ?? $cohort->campus_formation_id,
                    'name' => $cohort->campusFormation?->name ?? '(Formation supprimée)',
                ],
            ];
        });

        $byFormation = $cohortStats
            ->groupBy(fn ($c) => $c['formation']['id'])
            ->map(function ($cohorts) {
                $first      = $cohorts->first();
                $expected   = $cohorts->sum('total_expected');
                $collected  = $cohorts->sum('total_collected');
                $remaining  = $cohorts->sum('total_remaining');
                $overdue    = $cohorts->sum('overdue_count');

                return [
                    'id'      => $first['formation']['id'],
                    'name'    => $first['formation']['name'],
                    'cohorts' => $cohorts->values()->all(),
                    'totals'  => [
                        'total_expected'  => $expected,
                        'total_collected' => $collected,
                        'total_remaining' => $remaining,
                        'overdue_count'   => $overdue,
                        'collect_rate'    => $expected > 0
                            ? min(100, (int) round($collected / $expected * 100))
                            : 0,
                    ],
                ];
            })
            ->values();

        // Total planifié global = coût × actifs pour toutes les cohortes de l'année
        $totalExpected  = $cohortStats->sum('total_expected');
        $totalCollected = Payment::whereIn('cohort_id', $yearCohortIds)
            ->where('status', PaymentStatus::Paye)->sum('amount');

        $globalStats = [
            'total_expected'  => $totalExpected,
            'total_collected' => $totalCollected,
            'total_remaining' => max(0, $totalExpected - $totalCollected),
            'overdue_count'   => Payment::overdue()
                ->whereIn('cohort_id', $yearCohortIds)
                ->distinct('learner_id')
                ->count('learner_id'),
            'paid_this_month' => Payment::paid()
                ->whereIn('cohort_id', $yearCohortIds)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $recentOverdue = Payment::overdue()
            ->whereIn('cohort_id', $yearCohortIds)
            ->with(['learner', 'cohort' => fn ($q) => $q->with(['campusFormation' => fn ($q2) => $q2->withTrashed()])])
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'amount'   => $p->amount,
                'due_date' => $p->due_date->format('Y-m-d'),
                'learner'  => [
                    'id'         => $p->learner->id,
                    'first_name' => $p->learner->first_name,
                    'last_name'  => $p->learner->last_name,
                ],
                'cohort'   => [
                    'id'               => $p->cohort->id,
                    'name'             => $p->cohort->name,
                    'campus_formation' => ['name' => $p->cohort->campusFormation?->name ?? '(Formation supprimée)'],
                ],
            ]);

        return Inertia::render('Campus/Finance/Index', [
            'byFormation'  => $byFormation,
            'globalStats'  => $globalStats,
            'recentOverdue'=> $recentOverdue,
            'years'        => $years,
            'selectedYear' => $year,
        ]);
    }
}
