<?php

namespace App\Services;

use App\Models\Formation;
use App\Models\Form;
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
     * Generate a PDF summary of form candidatures.
     */
    public function formResponses(Form $form): Response
    {
        $form->load(['project:id,name', 'formation:id,name', 'fields']);

        $responses = $form->responses()
            ->with(['answers.field', 'reviewer:id,first_name,last_name'])
            ->latest('submitted_at')
            ->get();

        $pdf = Pdf::loadView('pdfs.form-responses', [
            'form' => $form,
            'responses' => $responses,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $filename = sprintf(
            'candidatures_%s_%s.pdf',
            str($form->title)->slug(),
            now()->format('Ymd-His')
        );

        return $pdf->download($filename);
    }
}
