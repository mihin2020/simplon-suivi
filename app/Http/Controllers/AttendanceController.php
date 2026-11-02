<?php

namespace App\Http\Controllers;

use App\Actions\RecordAttendance;
use App\Enums\AttendanceCode;
use App\Enums\DayType;
use App\Enums\RecapView;
use App\Http\Requests\Attendance\AttendanceDayRequest;
use App\Http\Requests\Attendance\AttendanceRecapRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\StoreSingleAttendanceRequest;
use App\Models\Formation;
use App\Services\AttendanceService;
use App\Support\AttendanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(AttendanceDayRequest $request, Formation $formation, AttendanceService $service): Response
    {
        $date = $request->date ? Carbon::parse($request->date)->startOfDay() : now()->startOfDay();
        $courseDays = $service->courseDays($formation);
        $dayType = $service->dayType($formation, $date);

        return Inertia::render('Attendances/Index', [
            ...$this->sharedProps($formation),
            'date' => $date->toDateString(),
            'dayType' => ['value' => $dayType->value, 'label' => $dayType->label()],
            // A non-course day can still be opened on purpose (exceptional class) or when already recorded.
            'isCourseDay' => $dayType === DayType::Course || $courseDays->contains($date->toDateString()),
            'isFuture' => $date->gt(now()),
            'allowExtra' => $request->boolean('extra'),
            'learners' => $service->dayRows($formation, $date),
            'dayStrip' => $service->dayStrip($formation, $courseDays),
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

    public function storeSingle(StoreSingleAttendanceRequest $request, Formation $formation, RecordAttendance $action): JsonResponse
    {
        $action->execute(
            formation: $formation,
            date: Carbon::parse($request->validated('date')),
            records: [$request->validated('learner_id') => [
                'code' => $request->validated('code'),
                'comment' => $request->validated('comment'),
            ]],
            recorder: $request->user(),
        );

        return response()->json(['ok' => true]);
    }

    public function recap(AttendanceRecapRequest $request, Formation $formation, AttendanceService $service): Response
    {
        $courseDays = $service->courseDays($formation);
        $period = $service->period($formation, $request->recapView(RecapView::Week), $request->validated('anchor'), $courseDays);

        return Inertia::render('Attendances/Index', [
            ...$this->sharedProps($formation),
            'date' => now()->toDateString(),
            'dayType' => ['value' => DayType::Course->value, 'label' => null],
            'isCourseDay' => true,
            'isFuture' => false,
            'allowExtra' => false,
            'learners' => [],
            'dayStrip' => [],
            'recap' => [
                'period' => $period ? [
                    'view' => $period->view->value,
                    'label' => $period->label,
                    'prev' => $period->prevAnchor,
                    'next' => $period->nextAnchor,
                    'anchor' => $period->from->toDateString(),
                ] : null,
                ...($period
                    ? $service->recap($formation, $period->from, $period->to, $courseDays)
                    : ['days' => [], 'weeks' => [], 'rows' => [], 'dayStats' => [], 'reasons' => []]),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function sharedProps(Formation $formation): array
    {
        return [
            'formation' => $formation->load('project'),
            'codes' => collect(AttendanceCode::cases())->map(fn (AttendanceCode $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'color' => $c->color(),
                'acceptsReason' => $c->acceptsReason(),
            ]),
            'attendanceSettings' => ['absenceAlertThreshold' => AttendanceSettings::absenceAlertThreshold()],
        ];
    }
}
