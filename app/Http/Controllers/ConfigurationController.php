<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use App\Models\TrainerProfile;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Configuration/Index', [
            'trainerProfiles' => TrainerProfile::orderBy('name')->get(['id', 'name']),
            'educationLevels' => EducationLevel::orderBy('created_at')->get(['id', 'name']),
        ]);
    }
}
