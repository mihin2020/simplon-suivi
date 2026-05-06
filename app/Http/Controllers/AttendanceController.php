<?php

namespace App\Http\Controllers;

use App\Actions\RecordAttendance;
use App\Enums\AttendanceCode;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\Formation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Formation $formation): Response
    {
        $this->authorize('create', [Attendance::class, $formation]);

        $date = request('date') ? Carbon::parse(request('date')) : now();

        $learners = $formation->activeLearners()
            ->orderBy('last_name')
            ->get()
            ->map(function ($learner) use ($formation, $date) {
                $attendance = $formation->attendances()
                    ->where('learner_id', $learner->id)
                    ->whereDate('date', $date)
                    ->first();

                return [
                    'id'         => $learner->id,
                    'full_name'  => $learner->full_name,
                    'attendance' => $attendance ? [
                        'code'    => $attendance->code->value,
                        'comment' => $attendance->comment,
                    ] : null,
                ];
            });

        return Inertia::render('Attendances/Index', [
            'formation' => $formation->load('project'),
            'date'      => $date->toDateString(),
            'learners'  => $learners,
            'codes'     => collect(AttendanceCode::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'color' => $c->color(),
            ]),
        ]);
    }

    public function store(StoreAttendanceRequest $request, Formation $formation, RecordAttendance $action): RedirectResponse
    {
        $action->execute(
            formation: $formation,
            date:      Carbon::parse($request->validated('date')),
            records:   $request->validated('records'),
            recorder:  $request->user(),
        );

        return redirect()
            ->route('attendances.index', ['formation' => $formation, 'date' => $request->validated('date')])
            ->with('success', 'Présences enregistrées avec succès.');
    }
}
