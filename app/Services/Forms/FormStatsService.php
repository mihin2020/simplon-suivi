<?php

namespace App\Services\Forms;

use App\Enums\FormResponseStatus;
use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FormStatsService
{
    /**
     * @return array{
     *     totals: array<string, int>,
     *     by_status: list<array{value: string, label: string, color: string, count: int}>,
     *     conversion: array{selection_rate: float, enrollment_rate: float, rejection_rate: float},
     *     daily: list<array{date: string, count: int}>,
     *     recent: list<array{id: string, email: string|null, status: string, status_label: string, status_color: string, submitted_at: string|null}>
     * }
     */
    public function forForm(Form $form): array
    {
        $counts = FormResponse::query()
            ->where('form_id', $form->id)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(FormResponseStatus::cases())->map(fn (FormResponseStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ])->values()->all();

        $total = array_sum(array_column($byStatus, 'count'));
        $selected = (int) ($counts[FormResponseStatus::Selected->value] ?? 0)
            + (int) ($counts[FormResponseStatus::Enrolled->value] ?? 0);
        $enrolled = (int) ($counts[FormResponseStatus::Enrolled->value] ?? 0);
        $rejected = (int) ($counts[FormResponseStatus::Rejected->value] ?? 0);

        $daily = $this->dailySubmissions($form, 14);

        $recent = FormResponse::query()
            ->where('form_id', $form->id)
            ->latest('submitted_at')
            ->limit(8)
            ->get()
            ->map(fn (FormResponse $r) => [
                'id' => $r->id,
                'email' => $r->email,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'status_color' => $r->status->color(),
                'submitted_at' => optional($r->submitted_at)->toIso8601String(),
            ])
            ->all();

        return [
            'totals' => [
                'responses' => $total,
                'submitted' => (int) ($counts[FormResponseStatus::Submitted->value] ?? 0)
                    + (int) ($counts[FormResponseStatus::Shortlisted->value] ?? 0),
                'selected' => $selected,
                'enrolled' => $enrolled,
                'rejected' => $rejected,
            ],
            'by_status' => $byStatus,
            'conversion' => [
                'selection_rate' => $total > 0 ? round(($selected / $total) * 100, 1) : 0.0,
                'enrollment_rate' => $total > 0 ? round(($enrolled / $total) * 100, 1) : 0.0,
                'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 1) : 0.0,
            ],
            'daily' => $daily,
            'recent' => $recent,
        ];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function dailySubmissions(Form $form, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $raw = FormResponse::query()
            ->where('form_id', $form->id)
            ->where('submitted_at', '>=', $from)
            ->select(DB::raw('DATE(submitted_at) as day'), DB::raw('count(*) as aggregate'))
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        /** @var Collection<string, int> $raw */
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'count' => (int) ($raw[$key] ?? 0),
            ];
        }

        return $series;
    }
}
