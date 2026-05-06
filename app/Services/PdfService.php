<?php

namespace App\Services;

use App\Models\Formation;
use App\Models\Learner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class PdfService
{
    /**
     * Generate a signed attendance sheet (feuille d'émargement) for a formation on a given date.
     */
    public function attendanceSheet(Formation $formation, Carbon $date): Response
    {
        $learners = $formation->activeLearners()
            ->with('educationLevel')
            ->orderBy('last_name')
            ->get();

        $pdf = Pdf::loadView('pdfs.attendance-sheet', [
            'formation' => $formation,
            'date'      => $date,
            'learners'  => $learners,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'emargement_%s_%s.pdf',
            str($formation->name)->slug(),
            $date->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Generate a learner list for a formation.
     */
    public function learnerList(Formation $formation): Response
    {
        $learners = $formation->activeLearners()
            ->with('educationLevel')
            ->orderBy('last_name')
            ->get();

        $pdf = Pdf::loadView('pdfs.learner-list', [
            'formation' => $formation,
            'learners'  => $learners,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('apprenants_%s.pdf', str($formation->name)->slug());

        return $pdf->download($filename);
    }
}
