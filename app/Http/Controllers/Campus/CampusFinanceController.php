<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Services\Campus\FinanceOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampusFinanceController extends Controller
{
    public function index(Request $request, FinanceOverview $overview): Response
    {
        $year = (int) $request->input('year', now()->year);

        return Inertia::render('Campus/Finance/Index', [
            ...$overview->forYear($year),
            'years' => $overview->years(),
            'selectedYear' => $year,
        ]);
    }
}
