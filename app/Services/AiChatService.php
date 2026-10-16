<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Referentiel;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    private const PROVIDERS = [
        'claude'   => ['url' => 'https://api.anthropic.com/v1/messages',                                                                   'default_model' => 'claude-opus-4-5'],
        'openai'   => ['url' => 'https://api.openai.com/v1/chat/completions',                                                              'default_model' => 'gpt-4o-mini'],
        'deepseek' => ['url' => 'https://api.deepseek.com/v1/chat/completions',                                                            'default_model' => 'deepseek-chat'],
        'grok'     => ['url' => 'https://api.x.ai/v1/chat/completions',                                                                    'default_model' => 'grok-3-mini'],
        'gemini'   => ['url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',                         'default_model' => 'gemini-2.0-flash'],
        'custom'   => ['url' => '',                                                                                                         'default_model' => ''],
    ];

    private const MAX_TOOL_ITERATIONS = 5;
    private const CACHE_TTL = 60; // secondes

    public function hasApiKey(): bool
    {
        return ! empty(AppSetting::get('ai_api_key'));
    }

    // ── Point d'entrée ───────────────────────────────────────────────────────

    public function chat(string $userMessage, array $history = []): string
    {
        $apiKey   = AppSetting::get('ai_api_key');
        $provider = AppSetting::get('ai_provider', 'openai');
        $model    = AppSetting::get('ai_model') ?: (self::PROVIDERS[$provider]['default_model'] ?? '');

        if (empty($apiKey)) {
            return "Aucune clé API configurée. Rendez-vous dans [Configuration](/configuration).";
        }

        $systemPrompt = $this->buildSystemPrompt();

        try {
            return match ($provider) {
                'claude' => $this->callClaude($apiKey, $model, $systemPrompt, $userMessage, $history),
                'gemini' => $this->callGemini($apiKey, $model, $systemPrompt, $userMessage, $history),
                default  => $this->callOpenAiCompatible($provider, $apiKey, $model, $systemPrompt, $userMessage, $history),
            };
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return "Le provider IA met trop de temps à répondre. Réessayez dans quelques instants.";
        } catch (\Exception $e) {
            Log::error('AI chat exception', ['provider' => $provider, 'message' => $e->getMessage()]);
            return "Impossible de contacter le provider IA. Vérifiez votre clé et votre connexion.";
        }
    }

    // ── Complétion one-shot (prompt système fourni, SANS outils) ─────────────

    /**
     * Contrairement à chat(), cette méthode sert à transformer du texte
     * (ex: convertir un modèle de contrat uploadé en HTML propre) et non à
     * répondre sur les données : elle n'expose donc ni les outils de requête
     * ni le prompt de l'assistant. En cas d'échec elle lève une exception,
     * pour que l'appelant puisse se replier au lieu d'insérer un message
     * d'erreur dans un document.
     *
     * @throws \RuntimeException
     */
    public function complete(string $systemPrompt, string $userMessage, int $maxTokens = 8192): string
    {
        $apiKey   = AppSetting::get('ai_api_key');
        $provider = AppSetting::get('ai_provider', 'openai');
        $model    = AppSetting::get('ai_model') ?: (self::PROVIDERS[$provider]['default_model'] ?? '');

        if (empty($apiKey)) {
            throw new \RuntimeException('Aucune clé API IA configurée.');
        }

        try {
            return match ($provider) {
                'claude' => $this->completeClaude($apiKey, $model, $systemPrompt, $userMessage, $maxTokens),
                'gemini' => $this->completeGemini($apiKey, $model, $systemPrompt, $userMessage, $maxTokens),
                default  => $this->completeOpenAiCompatible($provider, $apiKey, $model, $systemPrompt, $userMessage, $maxTokens),
            };
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new \RuntimeException('Le provider IA ne répond pas.');
        }
    }

    /**
     * Same as complete(), but reads the answer while it is being written and
     * reports the number of characters received so far to $onProgress, so a
     * caller can show real progress. Providers without streaming support here
     * (Gemini) fall back to complete() and report once, at the end.
     *
     * @param  callable(int): void  $onProgress
     *
     * @throws \RuntimeException
     */
    public function streamComplete(string $systemPrompt, string $userMessage, int $maxTokens, callable $onProgress): string
    {
        $apiKey   = AppSetting::get('ai_api_key');
        $provider = AppSetting::get('ai_provider', 'openai');
        $model    = AppSetting::get('ai_model') ?: (self::PROVIDERS[$provider]['default_model'] ?? '');

        if (empty($apiKey)) {
            throw new \RuntimeException('Aucune clé API IA configurée.');
        }

        if ($provider === 'gemini') {
            $text = $this->complete($systemPrompt, $userMessage, $maxTokens);
            $onProgress(mb_strlen($text));

            return $text;
        }

        if ($provider === 'claude') {
            $url = self::PROVIDERS['claude']['url'];
            $request = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ]);
            $payload = [
                'model'      => $model ?: 'claude-opus-4-5',
                'max_tokens' => $maxTokens,
                'stream'     => true,
                'system'     => $systemPrompt,
                'messages'   => [['role' => 'user', 'content' => $userMessage]],
            ];
            // event: content_block_delta → {"delta":{"type":"text_delta","text":"…"}}
            $extract = fn (array $event) => $event['delta']['text'] ?? null;
        } else {
            $url = AppSetting::get('ai_base_url') ?: (self::PROVIDERS[$provider]['url'] ?? self::PROVIDERS['openai']['url']);
            $request = Http::withToken($apiKey);
            $payload = [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'stream'     => true,
                'messages'   => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
            ];
            // data: {"choices":[{"delta":{"content":"…"}}]}
            $extract = fn (array $event) => $event['choices'][0]['delta']['content'] ?? null;
        }

        try {
            $response = $request->withOptions(['stream' => true])->timeout(150)->connectTimeout(10)->post($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new \RuntimeException('Le provider IA ne répond pas.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response, $provider));
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $text = '';

        // Server-sent events: one "data: {json}" line per chunk.
        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $event = json_decode(trim(substr($line, 5)), true);
                $chunk = is_array($event) ? $extract($event) : null;

                if (is_string($chunk) && $chunk !== '') {
                    $text .= $chunk;
                    $onProgress(mb_strlen($text));
                }
            }
        }

        if ($text === '') {
            throw new \RuntimeException('Réponse IA vide.');
        }

        return $text;
    }

    private function completeClaude(string $key, string $model, string $system, string $message, int $maxTokens): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->connectTimeout(10)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model ?: 'claude-opus-4-5',
            'max_tokens' => $maxTokens,
            'stream'     => false,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $message]],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response, 'Claude'));
        }

        $text = $this->extractClaudeText($response->json());

        if ($text === null || $text === '') {
            throw new \RuntimeException("Réponse IA vide.");
        }

        return $text;
    }

    private function completeOpenAiCompatible(string $provider, string $key, string $model, string $system, string $message, int $maxTokens): string
    {
        $url = AppSetting::get('ai_base_url') ?: (self::PROVIDERS[$provider]['url'] ?? self::PROVIDERS['openai']['url']);

        $response = Http::withToken($key)
            ->timeout(120)->connectTimeout(10)
            ->post($url, [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $message],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response, $provider));
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || $text === '') {
            throw new \RuntimeException("Réponse IA vide.");
        }

        return $text;
    }

    private function completeGemini(string $key, string $model, string $system, string $message, int $maxTokens): string
    {
        $model = $model ?: 'gemini-2.0-flash';
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $response = Http::timeout(120)->connectTimeout(10)->post($url, [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $message]]]],
            'generationConfig'  => ['maxOutputTokens' => $maxTokens],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->extractError($response, 'Gemini'));
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new \RuntimeException("Réponse IA vide.");
        }

        return $text;
    }

    // ── Prompt système léger (stats globales + navigation + instructions outils) ──

    private function buildSystemPrompt(): string
    {
        $baseUrl = config('app.url');
        $stats   = $this->dumpStats();

        return <<<PROMPT
Tu es l'assistant intelligent de la plateforme **Simplon Suivi** de Simplon Burkina Faso.

## Rôle
Tu réponds à des questions sur les données de l'application (projets, formations, apprenants, formateurs, présences, insertion/emploi, partenaires, référentiels, utilisateurs).

## Règles absolues
- Réponds TOUJOURS en français.
- Tu n'as PAS accès à toutes les données par défaut : **utilise les outils mis à ta disposition** pour interroger la base à chaque fois qu'une question porte sur des données précises (liste, détail, recherche, statistique). Ne réponds JAMAIS de mémoire ou en inventant : appelle un outil.
- Tu peux enchaîner plusieurs appels d'outils si une question nécessite plusieurs étapes (ex: trouver une formation puis lister ses apprenants).
- Si un outil retourne `total_matching` supérieur à `returned`, précise-le dans ta réponse et propose d'affiner la recherche (par formation, projet, etc.) plutôt que de prétendre avoir tout listé.
- Si une donnée demandée n'existe pas après avoir interrogé les outils pertinents, dis-le clairement plutôt que d'inventer.
- Formate les nombres en entiers clairs (ex: 12 apprenants, pas "environ 12").
- Formate les liens en Markdown : [Texte](URL complète), en remplaçant {id} par l'UUID réel retourné par les outils.
- Pour les tableaux, utilise le format Markdown.

## Vue d'ensemble immédiate (pas besoin d'outil pour ces chiffres globaux)
{$stats}

## Navigation de l'application
| Section | URL |
|---|---|
| Tableau de bord | $baseUrl/ |
| Projets (liste) | $baseUrl/projects |
| Formations (liste) | $baseUrl/formations |
| Apprenants (liste) | $baseUrl/learners |
| Formateurs (liste) | $baseUrl/trainers |
| Présences (accueil) | $baseUrl/presences |
| Partenaires (liste) | $baseUrl/partners |
| Référentiels (liste) | $baseUrl/referentiels |
| Statistiques | $baseUrl/statistics |
| Emails reçus | $baseUrl/communication/emails |
| WhatsApp | $baseUrl/communication/whatsapp |
| Utilisateurs | $baseUrl/users |
| Configuration | $baseUrl/configuration |

## URLs des fiches individuelles : remplace {id} par l'UUID réel retourné par les outils
- Fiche apprenant : $baseUrl/learners/{id}
- Suivi insertion d'un apprenant : $baseUrl/learners/{id}/insertion
- Fiche formation : $baseUrl/formations/{id}
- Présences d'une formation : $baseUrl/formations/{id}/attendances
- Fiche projet : $baseUrl/projects/{id}
- Fiche formateur : $baseUrl/trainers/{id}
PROMPT;
    }

    private function dumpStats(): string
    {
        return Cache::remember('ai_stats_dump', self::CACHE_TTL, function () {
            try {
                $lines = [
                    '| Entité | Total |',
                    '|---|---|',
                    '| Projets | ' . Project::count() . ' |',
                    '| Formations | ' . \App\Models\Formation::count() . ' |',
                    '| Apprenants | ' . Learner::count() . ' |',
                    '| Formateurs | ' . Trainer::count() . ' |',
                    '| Utilisateurs plateforme | ' . User::count() . ' |',
                    '| Partenaires | ' . Partner::count() . ' |',
                    '| Référentiels | ' . Referentiel::count() . ' |',
                ];

                $insertionCounts = InsertionRecord::selectRaw('status, count(*) as cnt')
                    ->groupBy('status')
                    ->pluck('cnt', 'status');

                if ($insertionCounts->isNotEmpty()) {
                    $lines[] = '| En stage | ' . ($insertionCounts['internship'] ?? 0) . ' |';
                    $lines[] = '| En emploi | ' . ($insertionCounts['employed'] ?? 0) . ' |';
                    $lines[] = '| En recherche | ' . ($insertionCounts['searching'] ?? 0) . ' |';
                }

                return implode("\n", $lines);
            } catch (\Exception) {
                return 'Indisponible.';
            }
        });
    }

    // Invalide le cache dès qu'une donnée change (appelé depuis les observers)
    public static function invalidateCache(): void
    {
        Cache::forget('ai_stats_dump');
    }

    // ── Appels API providers avec boucle de tool-calling ─────────────────────

    private function callClaude(string $key, string $model, string $system, string $message, array $history): string
    {
        $tools = array_map(fn ($t) => [
            'name'         => $t['name'],
            'description'  => $t['description'],
            'input_schema' => $t['parameters'],
        ], AiTools::definitions());

        $messages = $this->buildMessages($history, $message);

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = Http::withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->connectTimeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model ?: 'claude-opus-4-5',
                'max_tokens' => 4096,
                'stream'     => false,
                'system'     => $system,
                'tools'      => $tools,
                'messages'   => $messages,
            ]);

            if (! $response->successful()) {
                return $this->extractError($response, 'Claude');
            }

            $body = $response->json();

            if (($body['stop_reason'] ?? null) !== 'tool_use') {
                return $this->extractClaudeText($body) ?: "Aucune réponse générée.";
            }

            $assistantContent = array_map(function ($block) {
                if (($block['type'] ?? null) === 'tool_use' && empty($block['input'])) {
                    $block['input'] = new \stdClass();
                }
                return $block;
            }, $body['content'] ?? []);

            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

            $toolResults = [];
            foreach ($body['content'] ?? [] as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }
                $result = AiTools::execute($block['name'], $block['input'] ?? []);
                $toolResults[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return "La question nécessite trop d'étapes de recherche. Essayez de préciser votre demande (ex: une formation ou un apprenant précis).";
    }

    private function callOpenAiCompatible(string $provider, string $key, string $model, string $system, string $message, array $history): string
    {
        $url = AppSetting::get('ai_base_url') ?: (self::PROVIDERS[$provider]['url'] ?? self::PROVIDERS['openai']['url']);

        $tools = array_map(fn ($t) => [
            'type'     => 'function',
            'function' => [
                'name'        => $t['name'],
                'description' => $t['description'],
                'parameters'  => $t['parameters'],
            ],
        ], AiTools::definitions());

        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $this->buildMessages($history, $message),
        );

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ])->timeout(60)->connectTimeout(10)->post($url, [
                'model'      => $model,
                'messages'   => $messages,
                'tools'      => $tools,
                'max_tokens' => 4096,
                'stream'     => false,
            ]);

            if (! $response->successful()) {
                return $this->extractError($response, $provider);
            }

            $body          = $response->json();
            $choiceMessage = $body['choices'][0]['message'] ?? [];
            $toolCalls     = $choiceMessage['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                return $choiceMessage['content'] ?? "Aucune réponse générée.";
            }

            $messages[] = $choiceMessage;

            foreach ($toolCalls as $call) {
                $args   = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                $result = AiTools::execute($call['function']['name'], $args);

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return "La question nécessite trop d'étapes de recherche. Essayez de préciser votre demande (ex: une formation ou un apprenant précis).";
    }

    private function callGemini(string $key, string $model, string $system, string $message, array $history): string
    {
        $model = $model ?: 'gemini-2.0-flash';
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $functionDeclarations = array_map(fn ($t) => [
            'name'        => $t['name'],
            'description' => $t['description'],
            'parameters'  => $t['parameters'],
        ], AiTools::definitions());

        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)->connectTimeout(10)
                ->post($url, [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents'           => $contents,
                    'tools'              => [['function_declarations' => $functionDeclarations]],
                    'generationConfig'   => ['maxOutputTokens' => 4096],
                ]);

            if (! $response->successful()) {
                return $this->extractError($response, 'Gemini');
            }

            $body  = $response->json();
            $parts = $body['candidates'][0]['content']['parts'] ?? [];

            $functionCalls = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            if (empty($functionCalls)) {
                $text = collect($parts)->pluck('text')->filter()->implode('');
                return $text ?: "Aucune réponse générée.";
            }

            $sanitizedParts = array_map(function ($part) {
                if (isset($part['functionCall']) && empty($part['functionCall']['args'])) {
                    $part['functionCall']['args'] = new \stdClass();
                }
                return $part;
            }, $parts);

            $contents[] = ['role' => 'model', 'parts' => $sanitizedParts];

            $responseParts = [];
            foreach ($functionCalls as $part) {
                $call   = $part['functionCall'];
                $result = AiTools::execute($call['name'], $call['args'] ?? []);

                $responseParts[] = [
                    'functionResponse' => [
                        'name'     => $call['name'],
                        'response' => $result,
                    ],
                ];
            }
            $contents[] = ['role' => 'user', 'parts' => $responseParts];
        }

        return "La question nécessite trop d'étapes de recherche. Essayez de préciser votre demande (ex: une formation ou un apprenant précis).";
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildMessages(array $history, string $userMessage): array
    {
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    private function extractClaudeText(array $body): ?string
    {
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                return $block['text'];
            }
        }
        return null;
    }

    private function extractError(\Illuminate\Http\Client\Response $response, string $provider): string
    {
        $error = $response->json('error.message') ?? $response->json('error') ?? 'Erreur inconnue';
        Log::warning("AI error ({$provider})", ['status' => $response->status(), 'error' => $error]);

        return match ($response->status()) {
            401, 403 => "Clé API invalide. Vérifiez dans la [Configuration](/configuration).",
            429      => "Quota API dépassé. Vérifiez votre abonnement.",
            default  => "Erreur du provider ({$error}).",
        };
    }
}
