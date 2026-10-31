<?php

namespace App\Http\Controllers;

use App\Exports\FormationLearnersExport;
use App\Models\Formation;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormationLearnersExportController extends Controller
{
    public function __invoke(Formation $formation): BinaryFileResponse
    {
        $this->authorize('view', $formation);

        return Excel::download(
            new FormationLearnersExport($formation),
            sprintf('apprenants_%s_%s.xlsx', str($formation->name)->slug(), now()->format('Y-m-d')),
        );
    }
}
