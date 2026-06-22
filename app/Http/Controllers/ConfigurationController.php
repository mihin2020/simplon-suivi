<?php

namespace App\Http\Controllers;

use App\Http\Requests\Configuration\UpdateAttendanceSettingsRequest;
use App\Models\AgeRange;
use App\Models\AppSetting;
use App\Models\ContractType;
use App\Models\EducationLevel;
use App\Models\LastDiploma;
use App\Models\TrainerProfile;
use App\Models\Vulnerability;
use App\Support\AttendanceSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Configuration/Index', [
            'trainerProfiles' => TrainerProfile::orderBy('name')->get(['id', 'name']),
            'educationLevels' => EducationLevel::orderBy('created_at')->get(['id', 'name']),
            'ageRanges' => AgeRange::orderBy('order')->orderBy('age_min')->get(['id', 'name', 'age_min', 'age_max', 'order']),
            'vulnerabilities' => Vulnerability::orderBy('created_at')->get(['id', 'name']),
            'lastDiplomas' => LastDiploma::orderBy('created_at')->get(['id', 'name']),
            'internshipContractTypes' => ContractType::internship()->orderBy('order')->orderBy('name')->get(['id', 'name']),
            'employmentContractTypes' => ContractType::employment()->orderBy('order')->orderBy('name')->get(['id', 'name']),
            'aiConfig' => [
                'configured' => ! empty(AppSetting::get('ai_api_key')),
                'provider' => AppSetting::get('ai_provider', 'openai'),
                'model' => AppSetting::get('ai_model', ''),
                'base_url' => AppSetting::get('ai_base_url', ''),
            ],
            'attendanceSettings' => [
                'absenceAlertThreshold' => AttendanceSettings::absenceAlertThreshold(),
            ],
        ]);
    }

    public function updateAttendanceSettings(UpdateAttendanceSettingsRequest $request): RedirectResponse
    {
        AttendanceSettings::setAbsenceAlertThreshold(
            $request->validated('absence_alert_threshold'),
        );

        return redirect()
            ->route('configuration')
            ->with('success', 'Paramètres de présences enregistrés.');
    }
}
