<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiChatRequest;
use App\Models\AppSetting;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(private AiChatService $chatService) {}

    public function message(AiChatRequest $request): JsonResponse
    {
        // L'appel API IA peut dépasser la limite PHP par défaut (30s)
        set_time_limit(120);

        $reply = $this->chatService->chat(
            $request->validated('message'),
            $request->validated('history', []),
        );

        return response()->json(['reply' => $reply]);
    }

    public function saveApiKey(Request $request): RedirectResponse
    {
        $request->validate([
            'ai_provider' => ['required', 'in:claude,openai,deepseek,grok,gemini,custom'],
            'ai_api_key'  => ['required', 'string', 'min:10'],
            'ai_model'    => ['nullable', 'string', 'max:100'],
            'ai_base_url' => ['nullable', 'url', 'max:255'],
        ], [
            'ai_provider.required' => 'Sélectionnez un provider.',
            'ai_provider.in'       => 'Provider non reconnu.',
            'ai_api_key.required'  => 'La clé API est requise.',
            'ai_api_key.min'       => 'La clé API semble trop courte.',
            'ai_base_url.url'      => 'L\'URL de base doit être une URL valide.',
        ]);

        AppSetting::set('ai_provider', $request->ai_provider);
        AppSetting::set('ai_api_key', $request->ai_api_key, encrypt: true);
        AppSetting::set('ai_model', $request->ai_model ?? '');
        AppSetting::set('ai_base_url', $request->ai_base_url ?? '');

        return back()->with('success', 'Configuration IA enregistrée.');
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'configured' => $this->chatService->hasApiKey(),
            'provider'   => AppSetting::get('ai_provider', 'openai'),
        ]);
    }
}
