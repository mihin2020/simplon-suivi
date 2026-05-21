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

        $cohorts = Cohort::with(['campusFormation', 'payments'])
            ->withCount('learners')
            ->whereYear('started_at', $year)
            ->orderBy('started_at')
            ->get();

        $yearCohortIds = $cohorts->pluck('id')->all();

        $cohortStats = $cohorts->map(function (Cohort $cohort) {
            $payments     = $cohort->payments;
            $collected    = $payments->where('status', PaymentStatus::Paye)->sum('amount');
            $remaining    = $payments->whereIn('status', [PaymentStatus::EnAttente, PaymentStatus::EnRetard])->sum('amount');
            $expected     = $collected + $remaining;
            $overdueCount = $payments->filter(
                fn ($p) => $p->status === PaymentStatus::EnRetard
                    || ($p->status === PaymentStatus::EnAttente && $p->due_date?->isPast())
            )->count();

            return [
                'id'              => $cohort->id,
                'name'            => $cohort->name,
                'started_at'      => $cohort->started_at->format('Y-m-d'),
                'ended_at'        => $cohort->ended_at->format('Y-m-d'),
                'status'          => $cohort->status->value,
                'learners_count'  => $cohort->learners_count,
                'total_expected'  => $expected,
                'total_collected' => $collected,
                'total_remaining' => $remaining,
                'overdue_count'   => $overdueCount,
                'collect_rate'    => $expected > 0 ? round($collected / $expected * 100) : 0,
                'formation'       => [
                    'id'   => $cohort->campusFormation->id,
                    'name' => $cohort->campusFormation->name,
                ],
            ];
        });

        $byFormation = $cohortStats
            ->groupBy(fn ($c) => $c['formation']['id'])
            ->map(function ($cohorts) {
                $first  = $cohorts->first();
                $totals = [
                    'total_expected'  => $cohorts->sum('total_expected'),
                    'total_collected' => $cohorts->sum('total_collected'),
                    'total_remaining' => $cohorts->sum('total_remaining'),
                    'overdue_count'   => $cohorts->sum('overdue_count'),
                ];
                $totals['collect_rate'] = $totals['total_expected'] > 0
                    ? round($totals['total_collected'] / $totals['total_expected'] * 100)
                    : 0;

                return [
                    'id'      => $first['formation']['id'],
                    'name'    => $first['formation']['name'],
                    'cohorts' => $cohorts->values()->all(),
                    'totals'  => $totals,
                ];
            })
            ->values();

        $globalStats = [
            'total_collected' => Payment::whereIn('cohort_id', $yearCohortIds)
                ->where('status', PaymentStatus::Paye)->sum('amount'),
            'total_remaining' => Payment::whereIn('cohort_id', $yearCohortIds)
                ->whereIn('status', [PaymentStatus::EnAttente, PaymentStatus::EnRetard])->sum('amount'),
            'overdue_count'   => Payment::overdue()->whereIn('cohort_id', $yearCohortIds)->count(),
            'paid_this_month' => Payment::paid()
                ->whereIn('cohort_id', $yearCohortIds)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $recentOverdue = Payment::overdue()
            ->whereIn('cohort_id', $yearCohortIds)
            ->with(['learner', 'cohort.campusFormation'])
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
                    'campus_formation' => ['name' => $p->cohort->campusFormation->name],
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
