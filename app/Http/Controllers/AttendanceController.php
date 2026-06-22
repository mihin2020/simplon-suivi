<?php

namespace App\Http\Controllers;

use App\Actions\RecordAttendance;
use App\Enums\AttendanceCode;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\Formation;
use App\Support\AttendanceSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /** @return array<int, string> */
    private function absenceCodes(): array
    {
        return [
            AttendanceCode::AbsentJustified->value,
            AttendanceCode::AbsentNotJustified->value,
        ];
    }

    /** @param  iterable<string|null>  $dayCodes */
    private function countAbsences(iterable $dayCodes): int
    {
        $absenceCodes = $this->absenceCodes();

        return collect($dayCodes)
            ->filter(fn (?string $code) => $code && in_array($code, $absenceCodes, true))
            ->count();
    }

    private function attendanceSettingsPayload(): array
    {
        return [
            'absenceAlertThreshold' => AttendanceSettings::absenceAlertThreshold(),
        ];
    }

    public function index(Formation $formation): Response
    {
        $this->authorize('create', [Attendance::class, $formation]);

        $date = request('date') ? Carbon::parse(request('date')) : now();

        $learners = $formation->activeLearners()
            ->orderBy('first_name')
            ->get();

        $dayAttendances = $formation->attendances()
            ->whereDate('date', $date)
            ->get()
            ->keyBy('learner_id');

        $presentCodes = [
            AttendanceCode::Present->value,
            AttendanceCode::LateJustified->value,
            AttendanceCode::LateNotJustified->value,
        ];

        $allAttendancesByLearner = $formation->attendances()
            ->get()
            ->groupBy('learner_id');

        $learnerRows = $learners->map(function ($learner) use ($dayAttendances, $allAttendancesByLearner, $presentCodes) {
            $att = $dayAttendances->get($learner->id);
            $all = $allAttendancesByLearner->get($learner->id, collect());
            $total = $all->count();
            $pres = $all->filter(fn ($a) => in_array($a->code->value, $presentCodes))->count();
            $rate = $total > 0 ? round($pres / $total * 100) : null;

            return [
                'id' => $learner->id,
                'full_name' => $learner->full_name,
                'attendance' => $att ? ['code' => $att->code->value, 'comment' => $att->comment] : null,
                'rate' => $rate,
            ];
        });

        $savedDates = $formation->attendances()
            ->selectRaw('DATE(date) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day');

        return Inertia::render('Attendances/Index', [
            'formation' => $formation->load('project'),
            'date' => $date->toDateString(),
            'learners' => $learnerRows,
            'codes' => collect(AttendanceCode::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'color' => $c->color(),
            ]),
            'savedDates' => $savedDates,
            'attendanceSettings' => $this->attendanceSettingsPayload(),
        ]);
    }

    public function store(StoreAttendanceRequest $request, Formation $formation, RecordAttendance $action): RedirectResponse
    {
        $action->execute(
            formation: $formation,
            date: Carbon::parse($request->validated('date')),
            records: $request->validated('records'),
            recorder: $request->user(),
        );

        return redirect()
            ->route('attendances.index', ['formation' => $formation, 'date' => $request->validated('date')])
            ->with('success', 'Présences enregistrées avec succès.');
    }

    public function storeSingle(Request $request, Formation $formation): JsonResponse
    {
        $this->authorize('create', [Attendance::class, $formation]);

        $request->validate([
            'learner_id' => ['required', 'exists:learners,id'],
            'date' => ['required', 'date'],
            'code' => ['required', 'string', Rule::in(array_column(AttendanceCode::cases(), 'value'))],
        ]);

        Attendance::updateOrCreate(
            [
                'formation_id' => $formation->id,
                'learner_id' => $request->learner_id,
                'date' => Carbon::parse($request->date)->toDateString(),
            ],
            [
                'code' => AttendanceCode::from($request->code)->value,
                'recorded_by' => $request->user()->id,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function recap(Formation $formation): Response
    {
        $this->authorize('create', [Attendance::class, $formation]);

        $learners = $formation->activeLearners()
            ->orderBy('first_name')
            ->get();

        $allDates = $formation->attendances()
            ->selectRaw('DATE(date) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day');

        $presentCodes = [
            AttendanceCode::Present->value,
            AttendanceCode::LateJustified->value,
            AttendanceCode::LateNotJustified->value,
        ];

        $allAttendances = $formation->attendances()
            ->get()
            ->groupBy(fn ($a) => $a->learner_id.'_'.$a->date->toDateString());

        $rows = $learners->map(function ($learner) use ($allDates, $allAttendances, $presentCodes) {
            $days = $allDates->mapWithKeys(function ($day) use ($learner, $allAttendances) {
                $key = $learner->id.'_'.$day;
                $att = $allAttendances->get($key)?->first();

                return [$day => $att ? $att->code->value : null];
            });

            $total = $days->filter()->count();
            $present = $days->filter(fn ($c) => in_array($c, $presentCodes))->count();
            $rate = $total > 0 ? round($present / $total * 100) : null;
            $absences = $this->countAbsences($days);

            return [
                'id' => $learner->id,
                'full_name' => $learner->full_name,
                'days' => $days,
                'total' => $total,
                'present' => $present,
                'rate' => $rate,
                'absences' => $absences,
            ];
        });

        $dayStats = $allDates->mapWithKeys(function ($day) use ($learners, $allAttendances, $presentCodes) {
            $present = $learners->filter(function ($l) use ($day, $allAttendances, $presentCodes) {
                $key = $l->id.'_'.$day;
                $code = $allAttendances->get($key)?->first()?->code?->value;

                return in_array($code, $presentCodes);
            })->count();

            return [$day => ['present' => $present, 'total' => $learners->count()]];
        });

        return Inertia::render('Attendances/Index', [
            'formation' => $formation->load('project'),
            'date' => now()->toDateString(),
            'learners' => collect(),
            'codes' => collect(AttendanceCode::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'color' => $c->color(),
            ]),
            'savedDates' => $allDates,
            'recap' => [
                'dates' => $allDates,
                'rows' => $rows,
                'dayStats' => $dayStats,
            ],
            'attendanceSettings' => $this->attendanceSettingsPayload(),
        ]);
    }

    public function pdf(Request $request, Formation $formation): HttpResponse
    {
        $this->authorize('create', [Attendance::class, $formation]);

        $date = $request->has('date') ? Carbon::parse($request->date) : now();

        $learners = $formation->activeLearners()
            ->orderBy('first_name')
            ->get();

        $attendances = $formation->attendances()
            ->whereDate('date', $date)
            ->get()
            ->keyBy('learner_id');

        $pdf = Pdf::loadView('pdfs.attendance-day', [
            'formation' => $formation->load('project'),
            'date' => $date,
            'learners' => $learners,
            'attendances' => $attendances,
        ])->setPaper('a4', 'landscape');

        $filename = sprintf(
            'emargement_%s_%s.pdf',
            str($formation->name)->slug(),
            $date->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    public function pdfRecap(Formation $formation): HttpResponse
    {
        $this->authorize('create', [Attendance::class, $formation]);

        $learners = $formation->activeLearners()
            ->orderBy('first_name')
            ->get();

        $allDates = $formation->attendances()
            ->selectRaw('DATE(date) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day');

        $allAttendances = $formation->attendances()
            ->get()
            ->groupBy(fn ($a) => $a->learner_id.'_'.$a->date->toDateString());

        $rows = $learners->map(function ($learner) use ($allDates, $allAttendances) {
            $days = $allDates->mapWithKeys(function ($day) use ($learner, $allAttendances) {
                $key = $learner->id.'_'.$day;
                $att = $allAttendances->get($key)?->first();

                return [$day => $att ? $att->code->value : null];
            });

            return [
                'full_name' => $learner->full_name,
                'days' => $days,
                'absences' => $this->countAbsences($days),
            ];
        });

        $absenceAlertThreshold = AttendanceSettings::absenceAlertThreshold();

        // Paginer les dates : max 8 colonnes-jours par page A4 paysage pour confort de lecture
        $chunkSize = 8;
        $dateChunks = $allDates->chunk($chunkSize);

        $pdf = Pdf::loadView('pdfs.attendance-recap', [
            'formation' => $formation->load('project'),
            'rows' => $rows,
            'dateChunks' => $dateChunks,
            'totalDates' => $allDates->count(),
            'absenceAlertThreshold' => $absenceAlertThreshold,
        ])->setPaper('a4', 'landscape');

        $filename = sprintf('recap_presences_%s.pdf', str($formation->name)->slug());

        return $pdf->download($filename);
    }
}
