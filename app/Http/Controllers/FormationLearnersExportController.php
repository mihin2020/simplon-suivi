<?php

namespace App\Http\Controllers;

use App\Exports\FormationLearnersExport;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormationLearnersExportController extends Controller
{
    public function __invoke(Request $request, Formation $formation): BinaryFileResponse
    {
        $this->authorize('view', $formation);
        // The file holds personal data (CNIB, phone, vulnerability): it needs the learners permission too.
        $this->authorize('viewAny', Learner::class);

        Log::info('Formation learners exported', [
            'formation_id' => $formation->id,
            'user_id' => $request->user()->id,
        ]);

        return Excel::download(
            new FormationLearnersExport($formation),
            sprintf('apprenants_%s_%s.xlsx', str($formation->name)->slug(), now()->format('Y-m-d')),
        );
    }
}
