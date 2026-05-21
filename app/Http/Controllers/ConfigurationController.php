<?php

namespace App\Http\Controllers;

use App\Models\AgeRange;
use App\Models\AppSetting;
use App\Models\EducationLevel;
use App\Models\LastDiploma;
use App\Models\TrainerProfile;
use App\Models\Vulnerability;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
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
            'aiConfig' => [
                'configured' => ! empty(AppSetting::get('ai_api_key')),
                'provider'   => AppSetting::get('ai_provider', 'openai'),
                'model'      => AppSetting::get('ai_model', ''),
                'base_url'   => AppSetting::get('ai_base_url', ''),
            ],
            'whatsappConfig' => WhatsAppService::getConfig(),
        ]);
    }

    public function saveWhatsAppConfig(Request $request)
    {
        $validated = $request->validate([
            'twilio_sid'            => 'required|string|min:2',
            'twilio_token'          => 'required|string|min:2',
            'twilio_whatsapp_from'  => 'required|string|min:2',
        ]);

        AppSetting::set('twilio_sid',           $validated['twilio_sid'],           true);
        AppSetting::set('twilio_token',         $validated['twilio_token'],         true);
        AppSetting::set('twilio_whatsapp_from', $validated['twilio_whatsapp_from'], false);

        return back()->with('success', 'Configuration WhatsApp (Twilio) enregistrée.');
    }

    public function saveMetaWhatsAppConfig(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_meta_token'          => 'required|string|min:20',
            'whatsapp_meta_phone_id'         => 'required|string|min:5',
            'whatsapp_meta_business_id'      => 'nullable|string',
            'whatsapp_provider'              => 'required|in:twilio,meta',
        ]);

        AppSetting::set('whatsapp_meta_token',          $validated['whatsapp_meta_token'],          true);
        AppSetting::set('whatsapp_meta_phone_id',       $validated['whatsapp_meta_phone_id'],       false);
        AppSetting::set('whatsapp_meta_business_id',    $validated['whatsapp_meta_business_id'] ?? '', false);
        AppSetting::set('whatsapp_provider',            $validated['whatsapp_provider'],            false);

        return back()->with('success', 'Configuration WhatsApp Cloud API (Meta) enregistrée.');
    }
}
