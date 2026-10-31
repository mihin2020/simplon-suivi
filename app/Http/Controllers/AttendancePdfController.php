<?php

namespace App\Http\Controllers;

use App\Enums\RecapView;
use App\Http\Requests\Attendance\AttendanceDayRequest;
use App\Http\Requests\Attendance\AttendanceRecapRequest;
use App\Models\Formation;
use App\Services\AttendanceService;
use App\Support\AttendanceSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class AttendancePdfController extends Controller
{
    public function day(AttendanceDayRequest $request, Formation $formation, AttendanceService $service): Response
    {
        $date = $request->date ? Carbon::parse($request->date)->startOfDay() : now()->startOfDay();

        $rows = collect($service->dayRows($formation, $date))
            ->reject(fn (array $row) => $row['not_enrolled_yet'])
            ->values();

        $pdf = Pdf::loadView('pdfs.attendance-day', [
            'formation' => $formation->load('project'),
            'date' => $date,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape');

        return $pdf->download(sprintf('emargement_%s_%s.pdf', str($formation->name)->slug(), $date->format('Y-m-d')));
    }

    public function recap(AttendanceRecapRequest $request, Formation $formation, AttendanceService $service): Response
    {
        $period = $service->period($formation, $request->recapView(RecapView::All), $request->validated('anchor'));

        $pdf = Pdf::loadView('pdfs.attendance-recap', [
            'formation' => $formation->load('project'),
            'periodLabel' => $period?->label ?? 'Aucun jour de cours',
            'recap' => $period
                ? $service->recap($formation, $period->from, $period->to)
                : ['days' => [], 'weeks' => [], 'rows' => [], 'dayStats' => [], 'reasons' => []],
            'absenceAlertThreshold' => AttendanceSettings::absenceAlertThreshold(),
        ])->setPaper('a4', 'landscape');

        $suffix = $period ? $period->from->format('Y-m-d').'_'.$period->to->format('Y-m-d') : 'vide';

        return $pdf->download(sprintf('presences_%s_%s.pdf', str($formation->name)->slug(), $suffix));
    }
}
