<?php

namespace App\Http\Controllers\Learner;

use App\Exports\LearnerTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\LearnersImport;
use App\Models\Formation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportLearnerController extends Controller
{
    public function create(Request $request): Response
    {
        $formation = null;
        if ($request->query('formation')) {
            $formation = Formation::find($request->query('formation'));
        }

        return Inertia::render('Learners/Import', [
            'formation' => $formation,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file'         => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'formation_id' => ['nullable', 'uuid', 'exists:formations,id'],
        ]);

        $formationId = $validated['formation_id'] ?? null;

        $import = new LearnersImport($formationId);
        Excel::import($import, $request->file('file'));

        $redirect = $formationId
            ? redirect()->route('formations.show', $formationId)
            : redirect()->route('learners.index');

        if ($import->importedCount() === 0) {
            return $redirect->with('warning', "Aucun apprenant importé. Vérifie que les en-têtes correspondent au modèle (ex. 'prenom', 'nom') et que les lignes ne sont pas vides. Lignes ignorées : {$import->skippedCount()}.");
        }

        $message = "Import terminé : {$import->importedCount()} apprenant(s) importé(s).";
        if ($import->skippedCount() > 0) {
            $message .= " {$import->skippedCount()} ligne(s) ignorée(s) (champs obligatoires manquants ou email déjà existant).";
        }

        return $redirect->with('success', $message);
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new LearnerTemplateExport(),
            'modele_import_apprenants.xlsx'
        );
    }
}
