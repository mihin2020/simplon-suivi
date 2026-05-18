<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Attendance;
use App\Models\Competence;
use App\Models\CompetenceBlock;
use App\Models\EducationLevel;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Referentiel;
use App\Models\Trainer;
use App\Models\TrainerProfile;
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

    // ── Prompt système avec toutes les données BDD ───────────────────────────

    private function buildSystemPrompt(): string
    {
        $baseUrl = config('app.url');
        $data    = $this->loadAllData();

        return <<<PROMPT
Tu es l'assistant intelligent de la plateforme **Simplon Suivi** — Simplon Burkina Faso.

## Rôle
Tu as accès à toutes les données de l'application ci-dessous. Tu peux répondre à n'importe quelle question : lister, compter, filtrer, comparer, analyser.

## Règles absolues
- Réponds TOUJOURS en français.
- Utilise UNIQUEMENT les données fournies dans ce prompt. Ne t'invente rien.
- Si tu ne trouves pas une donnée, dis-le clairement plutôt que d'inventer.
- Pour les listes, sois exhaustif — donne TOUS les résultats correspondants, pas un sous-ensemble.
- Formate les nombres en entiers clairs (ex: 12 apprenants, pas "environ 12").
- Formate les liens en Markdown : [Texte](URL complète).
- Pour les tableaux, utilise le format Markdown.

## Navigation de l'application — toutes les pages accessibles
| Section | URL | Description |
|---|---|---|
| Tableau de bord | $baseUrl/ | Vue d'ensemble |
| Projets (liste) | $baseUrl/projects | Tous les projets |
| Formations (liste) | $baseUrl/formations | Toutes les formations |
| Apprenants (liste) | $baseUrl/learners | Tous les apprenants |
| Formateurs (liste) | $baseUrl/trainers | Tous les formateurs |
| Présences (accueil) | $baseUrl/presences | Redirection selon le rôle |
| Partenaires (liste) | $baseUrl/partners | Tous les partenaires |
| Référentiels (liste) | $baseUrl/referentiels | Tous les référentiels |
| Statistiques | $baseUrl/statistics | Tableau de bord statistiques |
| Emails reçus | $baseUrl/communication/emails | Boîte de réception |
| Emails envoyés | $baseUrl/communication/emails/sent | Emails envoyés |
| Composer un email | $baseUrl/communication/emails/compose | Rédiger un email |
| WhatsApp | $baseUrl/communication/whatsapp | Envoi groupé WhatsApp |
| Notifications | $baseUrl/notifications | Toutes les notifications |
| Utilisateurs | $baseUrl/users | Gestion des comptes |
| Configuration | $baseUrl/configuration | Paramètres de la plateforme |
| Mon profil | $baseUrl/profil | Profil de l'utilisateur connecté |

## URLs des fiches individuelles — utilise TOUJOURS l'ID réel fourni dans les données
**Apprenants**
- Fiche apprenant : $baseUrl/learners/{id}
- Suivi insertion/emploi d'un apprenant : $baseUrl/learners/{id}/insertion

**Formations**
- Fiche formation : $baseUrl/formations/{id}
- Présences d'une formation : $baseUrl/formations/{id}/attendances
- Récapitulatif présences : $baseUrl/formations/{id}/attendances/recap
- Médiathèque d'une formation : $baseUrl/formations/{id}/medias

**Projets**
- Fiche projet : $baseUrl/projects/{id}

**Formateurs**
- Fiche formateur : $baseUrl/trainers/{id}

**Communication**
- Fil de discussion email : $baseUrl/communication/emails/thread/{threadId}

> Règle : remplace toujours {id} ou {threadId} par la valeur réelle trouvée dans les données ci-dessous.

---

## DONNÉES COMPLÈTES DE L'APPLICATION

{$data}
PROMPT;
    }

    // ── Chargement complet des données ───────────────────────────────────────

    private const CACHE_TTL        = 60;   // secondes
    private const LEARNER_LIMIT_FULL    = 500; // en dessous : liste individuelle
    private const LEARNER_LIMIT_SUMMARY = 2000; // au-dessus : résumé seulement

    private function loadAllData(): string
    {
        // Cache court pour éviter N re-requêtes BDD par conversation
        return Cache::remember('ai_context_dump', self::CACHE_TTL, function () {
            $totalLearners = Learner::count();

            $sections = [];
            $sections[] = $this->dumpStats();
            $sections[] = $this->dumpProjects();
            $sections[] = $this->dumpFormations();

            if ($totalLearners <= self::LEARNER_LIMIT_FULL) {
                // Petit volume : liste complète individuelle
                $sections[] = $this->dumpLearners();
            } elseif ($totalLearners <= self::LEARNER_LIMIT_SUMMARY) {
                // Volume moyen : résumé par formation + liste individuelle des insertions
                $sections[] = $this->dumpLearnersByFormation();
                $sections[] = $this->dumpInsertions();
            } else {
                // Grand volume : statistiques par formation uniquement
                $sections[] = $this->dumpLearnersByFormation();
                $sections[] = "_(Volume important : {$totalLearners} apprenants — affichage agrégé par formation. Précisez une formation pour avoir la liste individuelle.)_";
            }

            $sections[] = $this->dumpTrainers();

            if ($totalLearners <= self::LEARNER_LIMIT_FULL) {
                $sections[] = $this->dumpInsertions();
            }

            $sections[] = $this->dumpAttendanceSummary();
            $sections[] = $this->dumpPartners();
            $sections[] = $this->dumpReferentiels();
            $sections[] = $this->dumpUsers();

            return implode("\n\n", array_filter($sections));
        });
    }

    // Invalide le cache dès qu'une donnée change (appelé depuis les observers ou controllers)
    public static function invalidateCache(): void
    {
        Cache::forget('ai_context_dump');
    }

    private function dumpStats(): string
    {
        try {
            $lines = [
                '### Statistiques globales',
                '| Entité | Total |',
                '|---|---|',
                '| Projets | ' . Project::count() . ' |',
                '| Formations | ' . Formation::count() . ' |',
                '| Apprenants | ' . Learner::count() . ' |',
                '| Apprenantes (F) | ' . Learner::where('gender', 'F')->count() . ' |',
                '| Apprenants (M) | ' . Learner::where('gender', 'M')->count() . ' |',
                '| Formateurs | ' . Trainer::count() . ' |',
                '| Utilisateurs | ' . User::count() . ' |',
                '| Partenaires | ' . Partner::count() . ' |',
                '| Référentiels | ' . Referentiel::count() . ' |',
                '| Utilisateurs plateforme | ' . User::count() . ' |',
            ];

            // Stats insertion
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
            return '### Statistiques globales\nIndisponible.';
        }
    }

    private function dumpProjects(): string
    {
        try {
            $projects = Project::select('id', 'name', 'start_date', 'end_date')
                ->withCount('formations')
                ->orderBy('name')
                ->get();

            if ($projects->isEmpty()) return '';

            $lines = ['### Projets (' . $projects->count() . ')'];
            $lines[] = '| ID | Nom | Début | Fin | Formations |';
            $lines[] = '|---|---|---|---|---|';

            foreach ($projects as $p) {
                $start = $p->start_date?->format('d/m/Y') ?? '—';
                $end   = $p->end_date?->format('d/m/Y') ?? '—';
                $lines[] = "| {$p->id} | {$p->name} | {$start} | {$end} | {$p->formations_count} |";
            }

            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpFormations(): string
    {
        try {
            $formations = Formation::select('id', 'name', 'project_id', 'start_date', 'end_date')
                ->with('project:id,name')
                ->withCount(['learners', 'trainers'])
                ->orderBy('name')
                ->get();

            if ($formations->isEmpty()) return '';

            $lines = ['### Formations (' . $formations->count() . ')'];
            $lines[] = '| ID | Nom | Projet | Début | Fin | Apprenants | Formateurs |';
            $lines[] = '|---|---|---|---|---|---|---|';

            foreach ($formations as $f) {
                $start   = $f->start_date?->format('d/m/Y') ?? '—';
                $end     = $f->end_date?->format('d/m/Y') ?? '—';
                $project = $f->project?->name ?? '—';
                $lines[] = "| {$f->id} | {$f->name} | {$project} | {$start} | {$end} | {$f->learners_count} | {$f->trainers_count} |";
            }

            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpLearners(): string
    {
        try {
            $learners = Learner::select('id', 'first_name', 'last_name', 'email', 'gender', 'phone', 'location')
                ->with([
                    'formations:id,name,project_id',
                    'formations.project:id,name',
                    'insertionRecords' => fn($q) => $q->orderByDesc('status_changed_at')->limit(1),
                ])
                ->orderBy('last_name')
                ->get();

            if ($learners->isEmpty()) return '';

            $lines = ['### Apprenants (' . $learners->count() . ')'];
            $lines[] = '| ID | Prénom | Nom | Genre | Email | Formation(s) | Projet(s) | Insertion | Entreprise |';
            $lines[] = '|---|---|---|---|---|---|---|---|---|';

            foreach ($learners as $l) {
                $gender     = match($l->gender?->value ?? '') { 'F' => 'F', 'M' => 'M', default => '—' };
                $formations = $l->formations->pluck('name')->join(', ') ?: '—';
                $projects   = $l->formations->map(fn($f) => $f->project?->name)->filter()->unique()->join(', ') ?: '—';
                $insertion  = $l->insertionRecords->first()?->status?->label() ?? '—';
                $company    = $l->insertionRecords->first()?->internship_company
                           ?? $l->insertionRecords->first()?->employment_company
                           ?? '—';

                $lines[] = "| {$l->id} | {$l->first_name} | {$l->last_name} | {$gender} | {$l->email} | {$formations} | {$projects} | {$insertion} | {$company} |";
            }

            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpLearnersByFormation(): string
    {
        try {
            $formations = Formation::select('id', 'name', 'project_id')
                ->with([
                    'project:id,name',
                    'learners' => fn($q) => $q->select('learners.id', 'first_name', 'last_name', 'gender', 'email')
                        ->with(['insertionRecords' => fn($q) => $q->orderByDesc('status_changed_at')->limit(1)]),
                ])
                ->get();

            if ($formations->isEmpty()) return '';

            $out = "### Apprenants par formation\n";

            foreach ($formations as $f) {
                $total  = $f->learners->count();
                $femmes = $f->learners->filter(fn($l) => $l->gender?->value === 'F')->count();
                $hommes = $f->learners->filter(fn($l) => $l->gender?->value === 'M')->count();
                $stages = $f->learners->filter(fn($l) => $l->insertionRecords->first()?->status?->value === 'internship')->count();
                $emploi = $f->learners->filter(fn($l) => $l->insertionRecords->first()?->status?->value === 'employed')->count();

                $out .= "\n**{$f->name}** (Projet : {$f->project?->name}) — {$total} apprenant(s) | ♀ {$femmes} | ♂ {$hommes} | En stage : {$stages} | En emploi : {$emploi}\n";

                // Liste individuelle par formation (toujours exhaustive par formation)
                foreach ($f->learners as $l) {
                    $gender    = $l->gender?->value === 'F' ? '♀' : ($l->gender?->value === 'M' ? '♂' : '—');
                    $insertion = $l->insertionRecords->first()?->status?->label() ?? '—';
                    $company   = $l->insertionRecords->first()?->internship_company ?? $l->insertionRecords->first()?->employment_company ?? '—';
                    $out      .= "  - {$gender} {$l->first_name} {$l->last_name} | {$l->email} | {$insertion}" . ($company !== '—' ? " chez {$company}" : '') . " | ID:{$l->id}\n";
                }
            }

            return $out;
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpPartners(): string
    {
        try {
            $partners = Partner::select('id', 'name', 'email', 'phone', 'website')->orderBy('name')->get();
            if ($partners->isEmpty()) return '';

            $lines = ['### Partenaires (' . $partners->count() . ')'];
            $lines[] = '| Nom | Email | Téléphone | Site web |';
            $lines[] = '|---|---|---|---|';
            foreach ($partners as $p) {
                $email   = $p->email   ?? '—';
                $phone   = $p->phone   ?? '—';
                $website = $p->website ?? '—';
                $lines[] = "| {$p->name} | {$email} | {$phone} | {$website} |";
            }
            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpReferentiels(): string
    {
        try {
            $referentiels = Referentiel::select('id', 'name')->with([
                'blocks' => fn($q) => $q->select('id', 'name', 'referentiel_id')->with('competences:id,name,competence_block_id'),
            ])->orderBy('name')->get();

            if ($referentiels->isEmpty()) return '';

            $out = '### Référentiels (' . $referentiels->count() . ")\n";
            foreach ($referentiels as $r) {
                $blockCount = $r->blocks->count();
                $compCount  = $r->blocks->sum(fn($b) => $b->competences->count());
                $out .= "\n**{$r->name}** — {$blockCount} bloc(s), {$compCount} compétence(s)\n";
                foreach ($r->blocks as $b) {
                    $out .= "  - Bloc : {$b->name}\n";
                    foreach ($b->competences as $c) {
                        $out .= "    · {$c->name}\n";
                    }
                }
            }
            return $out;
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpUsers(): string
    {
        try {
            $users = User::select('id', 'first_name', 'last_name', 'email', 'role', 'is_active')
                ->orderBy('last_name')
                ->get();

            if ($users->isEmpty()) return '';

            $lines = ['### Utilisateurs de la plateforme (' . $users->count() . ')'];
            $lines[] = '| Prénom | Nom | Email | Rôle | Actif |';
            $lines[] = '|---|---|---|---|---|';
            foreach ($users as $u) {
                $active = $u->is_active ? 'Oui' : 'Non';
                $role   = $u->role instanceof \BackedEnum ? $u->role->value : (string) $u->role;
                $lines[] = "| {$u->first_name} | {$u->last_name} | {$u->email} | {$role} | {$active} |";
            }
            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpTrainers(): string
    {
        try {
            $trainers = Trainer::select('id', 'first_name', 'last_name', 'email', 'phone')
                ->with('formations:id,name')
                ->orderBy('last_name')
                ->get();

            if ($trainers->isEmpty()) return '';

            $lines = ['### Formateurs (' . $trainers->count() . ')'];
            $lines[] = '| ID | Prénom | Nom | Email | Formations assignées |';
            $lines[] = '|---|---|---|---|---|';

            foreach ($trainers as $t) {
                $formations = $t->formations->pluck('name')->join(', ') ?: '—';
                $lines[] = "| {$t->id} | {$t->first_name} | {$t->last_name} | {$t->email} | {$formations} |";
            }

            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpInsertions(): string
    {
        try {
            $records = InsertionRecord::with(['learner:id,first_name,last_name'])
                ->orderByDesc('status_changed_at')
                ->get()
                ->unique('learner_id');

            if ($records->isEmpty()) return '';

            $lines = ['### Suivi insertion / emploi (' . $records->count() . ' apprenants)'];
            $lines[] = '| Apprenant | Statut | Entreprise | Contrat | Depuis |';
            $lines[] = '|---|---|---|---|---|';

            foreach ($records as $r) {
                if (! $r->learner) continue;
                $name     = "{$r->learner->first_name} {$r->learner->last_name}";
                $status   = $r->status->label();
                $company  = $r->internship_company ?? $r->employment_company ?? '—';
                $contract = $r->internship_contract_type ?? $r->employment_contract_type ?? '—';
                $since    = $r->internship_start_date?->format('d/m/Y') ?? $r->employment_start_date?->format('d/m/Y') ?? '—';
                $lines[]  = "| {$name} | {$status} | {$company} | {$contract} | {$since} |";
            }

            return implode("\n", $lines);
        } catch (\Exception) {
            return '';
        }
    }

    private function dumpAttendanceSummary(): string
    {
        try {
            $baseUrl = config('app.url');

            // Charger toutes les présences avec formation et apprenant
            $records = Attendance::select('formation_id', 'learner_id', 'date', 'code', 'comment')
                ->with([
                    'formation:id,name',
                    'learner:id,first_name,last_name',
                ])
                ->orderBy('formation_id')
                ->orderBy('date')
                ->orderBy('learner_id')
                ->get();

            if ($records->isEmpty()) return '';

            $total   = $records->count();
            $present = $records->where('code', \App\Enums\AttendanceCode::Present)->count();
            $aj      = $records->where('code', \App\Enums\AttendanceCode::AbsentJustified)->count();
            $an      = $records->where('code', \App\Enums\AttendanceCode::AbsentNotJustified)->count();
            $rj      = $records->where('code', \App\Enums\AttendanceCode::LateJustified)->count();
            $rn      = $records->where('code', \App\Enums\AttendanceCode::LateNotJustified)->count();
            $pct     = $total > 0 ? round(($present / $total) * 100, 1) : 0;

            $out  = "### Présences détaillées par formation et par date\n";
            $out .= "**Résumé global :** {$total} enregistrements | Présents : {$present} ({$pct}%) | AJ : {$aj} | AN : {$an} | RJ : {$rj} | RN : {$rn}\n\n";

            // Grouper par formation
            $byFormation = $records->groupBy('formation_id');

            foreach ($byFormation as $formationId => $formationRecords) {
                $formationName = $formationRecords->first()->formation?->name ?? 'Formation inconnue';
                $fTotal   = $formationRecords->count();
                $fPresent = $formationRecords->where('code', \App\Enums\AttendanceCode::Present)->count();
                $fPct     = $fTotal > 0 ? round(($fPresent / $fTotal) * 100, 1) : 0;

                $out .= "#### Formation : {$formationName} (ID : {$formationId})\n";
                $out .= "URL présences : {$baseUrl}/formations/{$formationId}/attendances\n";
                $out .= "Résumé : {$fTotal} entrées | Présents : {$fPresent} ({$fPct}%)\n\n";

                // Grouper par date
                $byDate = $formationRecords->groupBy(fn($r) => $r->date->format('Y-m-d'));

                foreach ($byDate as $dateKey => $dateRecords) {
                    $dateLabel  = $dateRecords->first()->date->format('d/m/Y');
                    $dPresent   = $dateRecords->where('code', \App\Enums\AttendanceCode::Present)->count();
                    $dTotal     = $dateRecords->count();
                    $out .= "**{$dateLabel}** — {$dPresent}/{$dTotal} présents\n";
                    $out .= "| Apprenant | Code | Signification | Commentaire |\n";
                    $out .= "|---|---|---|---|\n";

                    foreach ($dateRecords as $r) {
                        $learnerName = $r->learner
                            ? "{$r->learner->first_name} {$r->learner->last_name}"
                            : "Inconnu (ID:{$r->learner_id})";
                        $code        = $r->code instanceof \App\Enums\AttendanceCode ? $r->code->value : (string) $r->code;
                        $label       = $r->code instanceof \App\Enums\AttendanceCode ? $r->code->label() : $code;
                        $comment     = $r->comment ?? '—';
                        $out .= "| {$learnerName} | {$code} | {$label} | {$comment} |\n";
                    }
                    $out .= "\n";
                }
            }

            return $out;
        } catch (\Exception $e) {
            return '';
        }
    }

    // ── Appels API providers ─────────────────────────────────────────────────

    private function callClaude(string $key, string $model, string $system, string $message, array $history): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->connectTimeout(10)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model ?: 'claude-opus-4-5',
            'max_tokens' => 2048,
            'stream'     => false,
            'system'     => $system,
            'messages'   => $this->buildMessages($history, $message),
        ]);

        return $this->extract($response, fn($r) => $r['content'][0]['text'] ?? null, 'Claude');
    }

    private function callOpenAiCompatible(string $provider, string $key, string $model, string $system, string $message, array $history): string
    {
        $url = AppSetting::get('ai_base_url') ?: (self::PROVIDERS[$provider]['url'] ?? self::PROVIDERS['openai']['url']);

        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $this->buildMessages($history, $message),
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ])->timeout(60)->connectTimeout(10)->post($url, [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => 2048,
            'stream'     => false,
        ]);

        return $this->extract($response, fn($r) => $r['choices'][0]['message']['content'] ?? null, $provider);
    }

    private function callGemini(string $key, string $model, string $system, string $message, array $history): string
    {
        $model = $model ?: 'gemini-2.0-flash';
        $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)->connectTimeout(10)
            ->post($url, [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents'           => $contents,
                'generationConfig'   => ['maxOutputTokens' => 2048],
            ]);

        return $this->extract($response, fn($r) => $r['candidates'][0]['content']['parts'][0]['text'] ?? null, 'Gemini');
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

    private function extract(\Illuminate\Http\Client\Response $response, \Closure $extractor, string $provider): string
    {
        if ($response->successful()) {
            return $extractor($response->json()) ?? "Aucune réponse générée.";
        }

        $error = $response->json('error.message') ?? $response->json('error') ?? 'Erreur inconnue';
        Log::warning("AI error ({$provider})", ['status' => $response->status(), 'error' => $error]);

        return match ($response->status()) {
            401, 403 => "Clé API invalide. Vérifiez dans la [Configuration](/configuration).",
            429       => "Quota API dépassé. Vérifiez votre abonnement.",
            default   => "Erreur du provider ({$error}).",
        };
    }
}
