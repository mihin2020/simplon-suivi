<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'projects' => ['total' => 0, 'active' => 0],
                'formations' => ['total' => 0, 'active' => 0],
                'learners' => ['total' => 0, 'active' => 0],
                'trainers' => ['total' => 0],
                'insertion_rate' => 0,
            ],
        ]);
    }
}
