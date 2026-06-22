<?php

namespace App\Services;

use App\Enums\AttendanceCode;
use App\Enums\PaymentStatus;
use App\Models\AgeRange;
use App\Models\Attendance;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\EducationLevel;
use App\Models\Email;
use App\Models\Expense;
use App\Models\Formation;
use App\Models\FormationLink;
use App\Models\InsertionRecord;
use App\Models\LastDiploma;
use App\Models\Learner;
use App\Models\Media;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Referentiel;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use App\Models\Vulnerability;
use App\Models\WhatsAppMessage;

class AiTools
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 100;

    /**
     * Schémas JSON (communs aux providers) décrivant les outils disponibles.
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'search_learners',
                'description' => "Recherche des apprenants par nom/prénom/email, formation, projet ou statut d'insertion (recherche, stage, emploi, sans emploi). Retourne une liste bornée.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Nom, prénom ou email recherché'],
                        'formation_id' => ['type' => 'string', 'description' => "UUID d'une formation pour filtrer"],
                        'project_id' => ['type' => 'string', 'description' => "UUID d'un projet pour filtrer"],
                        'insertion_status' => ['type' => 'string', 'enum' => ['searching', 'internship', 'employed', 'unemployed']],
                        'limit' => ['type' => 'integer', 'description' => 'Nombre max de résultats (défaut 50, max 100)'],
                    ],
                ],
            ],
            [
                'name' => 'get_learner_detail',
                'description' => "Récupère la fiche complète d'un apprenant : formations, statut d'insertion, résumé des présences.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['learner_id' => ['type' => 'string', 'description' => "UUID de l'apprenant"]],
                    'required' => ['learner_id'],
                ],
            ],
            [
                'name' => 'list_projects',
                'description' => 'Liste tous les projets avec leurs dates et leur nombre de formations.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'list_formations',
                'description' => "Liste les formations (éventuellement filtrées par projet) avec le nombre d'apprenants et de formateurs.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['project_id' => ['type' => 'string', 'description' => "UUID d'un projet pour filtrer"]],
                ],
            ],
            [
                'name' => 'get_formation_detail',
                'description' => "Détail d'une formation : apprenants inscrits, formateurs assignés, taux de présence global.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['formation_id' => ['type' => 'string', 'description' => 'UUID de la formation']],
                    'required' => ['formation_id'],
                ],
            ],
            [
                'name' => 'list_trainers',
                'description' => 'Liste les formateurs, éventuellement filtrés par formation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['formation_id' => ['type' => 'string', 'description' => "UUID d'une formation pour filtrer"]],
                ],
            ],
            [
                'name' => 'get_attendance_stats',
                'description' => 'Statistiques agrégées de présence pour une formation (taux de présence, absences justifiées/non justifiées, retards), sur une période optionnelle. Ne retourne PAS le détail ligne par ligne.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'formation_id' => ['type' => 'string', 'description' => 'UUID de la formation'],
                        'date_from' => ['type' => 'string', 'description' => 'Date de début (YYYY-MM-DD), optionnel'],
                        'date_to' => ['type' => 'string', 'description' => 'Date de fin (YYYY-MM-DD), optionnel'],
                    ],
                    'required' => ['formation_id'],
                ],
            ],
            [
                'name' => 'get_attendance_detail',
                'description' => 'Détail des présences de TOUS les apprenants pour UNE seule date et UNE formation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'formation_id' => ['type' => 'string', 'description' => 'UUID de la formation'],
                        'date' => ['type' => 'string', 'description' => 'Date au format YYYY-MM-DD'],
                    ],
                    'required' => ['formation_id', 'date'],
                ],
            ],
            [
                'name' => 'list_insertions',
                'description' => 'Liste le suivi insertion/emploi des apprenants (dernier statut connu par apprenant), filtrable par statut ou formation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['searching', 'internship', 'employed', 'unemployed']],
                        'formation_id' => ['type' => 'string', 'description' => "UUID d'une formation pour filtrer"],
                        'limit' => ['type' => 'integer', 'description' => 'Nombre max de résultats (défaut 50, max 100)'],
                    ],
                ],
            ],
            [
                'name' => 'list_partners',
                'description' => 'Liste tous les partenaires de la plateforme avec leurs contacts.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'list_referentiels',
                'description' => 'Liste les référentiels (et leurs blocs de compétences si un ID est précisé).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['referentiel_id' => ['type' => 'string', 'description' => "UUID d'un référentiel pour avoir le détail complet (blocs + compétences)"]],
                ],
            ],
            [
                'name' => 'list_users',
                'description' => 'Liste les utilisateurs de la plateforme (comptes Admin/Formateur/Super Admin) avec leur rôle.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'list_campus_formations',
                'description' => 'Liste les formations Campus (catalogue payant : nom, durée, mode présentiel/en ligne, coût, nombre de cohortes).',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'list_cohorts',
                'description' => "Liste les cohortes Campus, avec leur formation, statut, dates, nombre d'apprenants actifs et résumé financier (attendu/collecté/restant).",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'campus_formation_id' => ['type' => 'string', 'description' => "UUID d'une formation Campus pour filtrer"],
                        'status' => ['type' => 'string', 'enum' => ['planifiee', 'en_cours', 'cloturee']],
                    ],
                ],
            ],
            [
                'name' => 'get_cohort_finance_detail',
                'description' => "Détail financier d'une cohorte : montants attendu/collecté/restant, tranches en retard, et paiement par apprenant.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['cohort_id' => ['type' => 'string', 'description' => 'UUID de la cohorte']],
                    'required' => ['cohort_id'],
                ],
            ],
            [
                'name' => 'list_expenses',
                'description' => 'Liste les dépenses enregistrées (par formation classique, pas Campus), avec le total cumulé. Filtrable par formation et par période.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'formation_id' => ['type' => 'string', 'description' => "UUID d'une formation pour filtrer"],
                        'date_from' => ['type' => 'string', 'description' => 'Date de début (YYYY-MM-DD)'],
                        'date_to' => ['type' => 'string', 'description' => 'Date de fin (YYYY-MM-DD)'],
                        'limit' => ['type' => 'integer', 'description' => 'Nombre max de résultats (défaut 50, max 100)'],
                    ],
                ],
            ],
            [
                'name' => 'list_formation_links',
                'description' => 'Liste les liens/ressources partagés pour une formation (visioconférence, documents externes, etc.).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['formation_id' => ['type' => 'string', 'description' => 'UUID de la formation']],
                    'required' => ['formation_id'],
                ],
            ],
            [
                'name' => 'list_media',
                'description' => "Liste les médias (photos/vidéos/documents) de la médiathèque d'une formation, éventuellement filtrés par album.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'formation_id' => ['type' => 'string', 'description' => 'UUID de la formation'],
                        'album' => ['type' => 'string', 'description' => "Nom de l'album pour filtrer"],
                        'limit' => ['type' => 'integer', 'description' => 'Nombre max de résultats (défaut 50, max 100)'],
                    ],
                    'required' => ['formation_id'],
                ],
            ],
            [
                'name' => 'list_emails',
                'description' => 'Liste les emails envoyés/reçus par la plateforme (boîte de communication), filtrable par sens, lecture, ou fil de discussion.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'direction' => ['type' => 'string', 'enum' => ['sent', 'received']],
                        'is_read' => ['type' => 'boolean'],
                        'thread_id' => ['type' => 'string', 'description' => 'Identifiant du fil de discussion'],
                        'limit' => ['type' => 'integer', 'description' => 'Nombre max de résultats (défaut 50, max 100)'],
                    ],
                ],
            ],
            [
                'name' => 'list_whatsapp_messages',
                'description' => 'Liste les messages WhatsApp générés/envoyés (liens wa.me), filtrable par apprenant ou statut.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'learner_id' => ['type' => 'string', 'description' => "UUID d'un apprenant pour filtrer"],
                        'status' => ['type' => 'string', 'description' => 'Statut du message'],
                        'limit' => ['type' => 'integer', 'description' => 'Nombre max de résultats (défaut 50, max 100)'],
                    ],
                ],
            ],
            [
                'name' => 'list_reference_data',
                'description' => "Liste une table de données de référence/configuration : niveaux d'étude, tranches d'âge, vulnérabilités, derniers diplômes, ou rôles applicatifs.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['education_levels', 'age_ranges', 'vulnerabilities', 'last_diplomas', 'roles']],
                    ],
                    'required' => ['type'],
                ],
            ],
        ];
    }

    public static function execute(string $name, array $args): array
    {
        try {
            return match ($name) {
                'search_learners' => self::searchLearners($args),
                'get_learner_detail' => self::getLearnerDetail($args),
                'list_projects' => self::listProjects(),
                'list_formations' => self::listFormations($args),
                'get_formation_detail' => self::getFormationDetail($args),
                'list_trainers' => self::listTrainers($args),
                'get_attendance_stats' => self::getAttendanceStats($args),
                'get_attendance_detail' => self::getAttendanceDetail($args),
                'list_insertions' => self::listInsertions($args),
                'list_partners' => self::listPartners(),
                'list_referentiels' => self::listReferentiels($args),
                'list_users' => self::listUsers(),
                'list_campus_formations' => self::listCampusFormations(),
                'list_cohorts' => self::listCohorts($args),
                'get_cohort_finance_detail' => self::getCohortFinanceDetail($args),
                'list_expenses' => self::listExpenses($args),
                'list_formation_links' => self::listFormationLinks($args),
                'list_media' => self::listMedia($args),
                'list_emails' => self::listEmails($args),
                'list_whatsapp_messages' => self::listWhatsappMessages($args),
                'list_reference_data' => self::listReferenceData($args),
                default => ['error' => "Outil inconnu : {$name}"],
            };
        } catch (\Exception $e) {
            return ['error' => "Échec de l'outil {$name} : {$e->getMessage()}"];
        }
    }

    private static function clampLimit(array $args): int
    {
        return min((int) ($args['limit'] ?? self::DEFAULT_LIMIT) ?: self::DEFAULT_LIMIT, self::MAX_LIMIT);
    }

    private static function searchLearners(array $args): array
    {
        $limit = self::clampLimit($args);

        $query = Learner::query()
            ->select('id', 'first_name', 'last_name', 'email', 'gender', 'phone')
            ->with([
                'formations:id,name,project_id',
                'formations.project:id,name',
                'insertionRecords' => fn ($q) => $q->limit(1),
            ]);

        if (! empty($args['query'])) {
            $query->search($args['query']);
        }
        if (! empty($args['formation_id'])) {
            $query->whereHas('formations', fn ($f) => $f->where('formations.id', $args['formation_id']));
        }
        if (! empty($args['project_id'])) {
            $query->whereHas('formations', fn ($f) => $f->where('project_id', $args['project_id']));
        }

        // Statut d'insertion : filtré côté collection sur le dernier enregistrement
        // (un pré-cap SQL évite de charger toute la table en l'absence d'autres filtres).
        $hasOtherFilter = ! empty($args['query']) || ! empty($args['formation_id']) || ! empty($args['project_id']);
        $preCap = $hasOtherFilter ? 1000 : 500;

        $learners = $query->limit($preCap)->get();

        if (! empty($args['insertion_status'])) {
            $learners = $learners->filter(
                fn ($l) => $l->insertionRecords->first()?->status?->value === $args['insertion_status']
            );
        }

        $total = $learners->count();
        $page = $learners->take($limit);

        return [
            'total_matching' => $total,
            'returned' => $page->count(),
            'learners' => $page->map(fn ($l) => [
                'id' => $l->id,
                'name' => "{$l->first_name} {$l->last_name}",
                'email' => $l->email,
                'phone' => $l->phone,
                'gender' => $l->gender?->value,
                'formations' => $l->formations->pluck('name')->all(),
                'insertion_status' => $l->insertionRecords->first()?->status?->label(),
            ])->values()->all(),
        ];
    }

    private static function getLearnerDetail(array $args): array
    {
        $learner = Learner::with([
            'formations:id,name,project_id',
            'formations.project:id,name',
            'insertionRecords',
        ])->find($args['learner_id'] ?? null);

        if (! $learner) {
            return ['error' => 'Apprenant introuvable.'];
        }

        $attendanceCounts = Attendance::where('learner_id', $learner->id)
            ->selectRaw('code, count(*) as cnt')
            ->groupBy('code')
            ->pluck('cnt', 'code');

        return [
            'id' => $learner->id,
            'name' => "{$learner->first_name} {$learner->last_name}",
            'email' => $learner->email,
            'phone' => $learner->phone,
            'gender' => $learner->gender?->value,
            'formations' => $learner->formations->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'project' => $f->project?->name,
                'status' => $f->pivot->status,
            ])->all(),
            'insertion_history' => $learner->insertionRecords->map(fn ($r) => [
                'status' => $r->status->label(),
                'since' => $r->status_changed_at?->format('d/m/Y'),
                'company' => $r->internship_company ?? $r->employment_company,
            ])->all(),
            'attendance_summary' => $attendanceCounts,
        ];
    }

    private static function listProjects(): array
    {
        $projects = Project::select('id', 'name', 'started_at', 'ended_at', 'status')
            ->withCount('formations')
            ->orderBy('name')
            ->get();

        return ['projects' => $projects->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'started_at' => $p->started_at?->format('d/m/Y'),
            'ended_at' => $p->ended_at?->format('d/m/Y'),
            'status' => $p->status?->value,
            'formations_count' => $p->formations_count,
        ])->all()];
    }

    private static function listFormations(array $args): array
    {
        $query = Formation::select('id', 'name', 'project_id', 'started_at', 'ended_at', 'status')
            ->with('project:id,name')
            ->withCount(['learners', 'trainers']);

        if (! empty($args['project_id'])) {
            $query->where('project_id', $args['project_id']);
        }

        $formations = $query->orderBy('name')->get();

        return ['formations' => $formations->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'project' => $f->project?->name,
            'started_at' => $f->started_at?->format('d/m/Y'),
            'ended_at' => $f->ended_at?->format('d/m/Y'),
            'status' => $f->status?->value,
            'learners_count' => $f->learners_count,
            'trainers_count' => $f->trainers_count,
        ])->all()];
    }

    private static function getFormationDetail(array $args): array
    {
        $formation = Formation::with([
            'project:id,name',
            'learners:learners.id,first_name,last_name,email',
            'trainers.user:id,first_name,last_name',
        ])->find($args['formation_id'] ?? null);

        if (! $formation) {
            return ['error' => 'Formation introuvable.'];
        }

        $attendanceCounts = Attendance::where('formation_id', $formation->id)
            ->selectRaw('code, count(*) as cnt')
            ->groupBy('code')
            ->pluck('cnt', 'code');

        $total = $attendanceCounts->sum();
        $present = (int) ($attendanceCounts[AttendanceCode::Present->value] ?? 0);

        return [
            'id' => $formation->id,
            'name' => $formation->name,
            'project' => $formation->project?->name,
            'started_at' => $formation->started_at?->format('d/m/Y'),
            'ended_at' => $formation->ended_at?->format('d/m/Y'),
            'status' => $formation->status?->value,
            'learners' => $formation->learners->map(fn ($l) => [
                'id' => $l->id, 'name' => "{$l->first_name} {$l->last_name}", 'email' => $l->email,
                'status' => $l->pivot->status,
            ])->all(),
            'trainers' => $formation->trainers->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->user?->full_name, 'is_lead' => (bool) $t->pivot->is_lead,
            ])->all(),
            'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1).'%' : 'Aucune donnée',
        ];
    }

    private static function listTrainers(array $args): array
    {
        $query = Trainer::active()->with(['user:id,first_name,last_name,email', 'formations:id,name']);

        if (! empty($args['formation_id'])) {
            $query->whereHas('formations', fn ($f) => $f->where('formations.id', $args['formation_id']));
        }

        $trainers = $query->get();

        return ['trainers' => $trainers->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->user?->full_name,
            'email' => $t->user?->email,
            'phone' => $t->phone,
            'formations' => $t->formations->pluck('name')->all(),
        ])->all()];
    }

    private static function getAttendanceStats(array $args): array
    {
        $query = Attendance::where('formation_id', $args['formation_id'] ?? '');

        if (! empty($args['date_from'])) {
            $query->whereDate('date', '>=', $args['date_from']);
        }
        if (! empty($args['date_to'])) {
            $query->whereDate('date', '<=', $args['date_to']);
        }

        $counts = (clone $query)->selectRaw('code, count(*) as cnt')->groupBy('code')->pluck('cnt', 'code');
        $total = $counts->sum();
        $dates = (clone $query)->selectRaw('min(date) as min_date, max(date) as max_date')->first();

        return [
            'total_entries' => $total,
            'present' => (int) ($counts[AttendanceCode::Present->value] ?? 0),
            'absent_justified' => (int) ($counts[AttendanceCode::AbsentJustified->value] ?? 0),
            'absent_unjustified' => (int) ($counts[AttendanceCode::AbsentNotJustified->value] ?? 0),
            'late_justified' => (int) ($counts[AttendanceCode::LateJustified->value] ?? 0),
            'late_unjustified' => (int) ($counts[AttendanceCode::LateNotJustified->value] ?? 0),
            'presence_rate' => $total > 0 ? round((($counts[AttendanceCode::Present->value] ?? 0) / $total) * 100, 1).'%' : 'N/A',
            'period' => [
                'from' => $dates?->min_date,
                'to' => $dates?->max_date,
            ],
        ];
    }

    private static function getAttendanceDetail(array $args): array
    {
        $records = Attendance::where('formation_id', $args['formation_id'] ?? '')
            ->whereDate('date', $args['date'] ?? '')
            ->with('learner:id,first_name,last_name')
            ->get();

        if ($records->isEmpty()) {
            return ['entries' => [], 'message' => 'Aucune présence enregistrée pour cette date.'];
        }

        return ['entries' => $records->map(fn ($r) => [
            'learner' => $r->learner ? "{$r->learner->first_name} {$r->learner->last_name}" : "ID:{$r->learner_id}",
            'code' => $r->code->value,
            'label' => $r->code->label(),
            'comment' => $r->comment,
        ])->all()];
    }

    private static function listInsertions(array $args): array
    {
        $limit = self::clampLimit($args);

        $query = InsertionRecord::with(['learner:id,first_name,last_name'])
            ->orderByDesc('status_changed_at');

        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }
        if (! empty($args['formation_id'])) {
            $query->whereHas('learner.formations', fn ($f) => $f->where('formations.id', $args['formation_id']));
        }

        $records = $query->get()->unique('learner_id');
        $total = $records->count();
        $page = $records->take($limit);

        return [
            'total_matching' => $total,
            'returned' => $page->count(),
            'records' => $page->filter(fn ($r) => $r->learner)->map(fn ($r) => [
                'learner' => "{$r->learner->first_name} {$r->learner->last_name}",
                'status' => $r->status->label(),
                'company' => $r->internship_company ?? $r->employment_company,
                'contract' => $r->internship_contract_type ?? $r->employment_contract_type,
                'since' => $r->internship_start_date?->format('d/m/Y') ?? $r->employment_start_date?->format('d/m/Y'),
            ])->values()->all(),
        ];
    }

    private static function listPartners(): array
    {
        $partners = Partner::select('id', 'name', 'category', 'contact_first_name', 'contact_last_name', 'contact_email', 'contact_phone', 'contact_position')
            ->orderBy('name')
            ->get();

        return ['partners' => $partners->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category?->value,
            'category_label' => $p->category?->label(),
            'contact' => trim("{$p->contact_first_name} {$p->contact_last_name}") ?: null,
            'email' => $p->contact_email,
            'phone' => $p->contact_phone,
            'position' => $p->contact_position,
        ])->all()];
    }

    private static function listReferentiels(array $args): array
    {
        if (! empty($args['referentiel_id'])) {
            $r = Referentiel::with(['blocks.competences'])->find($args['referentiel_id']);
            if (! $r) {
                return ['error' => 'Référentiel introuvable.'];
            }

            return ['referentiel' => [
                'id' => $r->id,
                'name' => $r->name,
                'blocks' => $r->blocks->map(fn ($b) => [
                    'name' => $b->name,
                    'competences' => $b->competences->pluck('name')->all(),
                ])->all(),
            ]];
        }

        $referentiels = Referentiel::withCount('blocks')->orderBy('name')->get();

        return ['referentiels' => $referentiels->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'blocks_count' => $r->blocks_count,
        ])->all()];
    }

    private static function listUsers(): array
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'role', 'is_active')
            ->orderBy('last_name')
            ->get();

        return ['users' => $users->map(fn ($u) => [
            'name' => "{$u->first_name} {$u->last_name}",
            'email' => $u->email,
            'role' => $u->role?->value,
            'active' => $u->is_active,
        ])->all()];
    }

    private static function listCampusFormations(): array
    {
        $formations = CampusFormation::withCount('cohorts')->orderBy('name')->get();

        return ['campus_formations' => $formations->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'mode' => $f->mode?->label(),
            'duration_months' => $f->duration_months,
            'total_cost' => $f->total_cost,
            'is_active' => $f->is_active,
            'cohorts_count' => $f->cohorts_count,
        ])->all()];
    }

    private static function listCohorts(array $args): array
    {
        $query = Cohort::with(['campusFormation' => fn ($q) => $q->withTrashed()])
            ->withCount(['learners as active_learners_count' => fn ($q) => $q->wherePivot('status', 'actif')]);

        if (! empty($args['campus_formation_id'])) {
            $query->where('campus_formation_id', $args['campus_formation_id']);
        }
        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $cohorts = $query->orderByDesc('started_at')->get();

        return ['cohorts' => $cohorts->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'campus_formation' => $c->campusFormation?->name,
            'status' => $c->status?->label(),
            'started_at' => $c->started_at?->format('d/m/Y'),
            'ended_at' => $c->ended_at?->format('d/m/Y'),
            'active_learners' => $c->active_learners_count,
            'total_expected' => $c->total_expected,
            'total_collected' => $c->total_collected,
            'total_remaining' => $c->total_remaining,
        ])->all()];
    }

    private static function getCohortFinanceDetail(array $args): array
    {
        $cohort = Cohort::with(['campusFormation' => fn ($q) => $q->withTrashed()])->find($args['cohort_id'] ?? null);

        if (! $cohort) {
            return ['error' => 'Cohorte introuvable.'];
        }

        $overdueCount = $cohort->payments()->overdue()->distinct('learner_id')->count('learner_id');

        $perLearner = $cohort->payments()
            ->with('learner:id,first_name,last_name')
            ->get()
            ->groupBy('learner_id')
            ->map(function ($payments) {
                $learner = $payments->first()->learner;

                return [
                    'learner' => $learner ? "{$learner->first_name} {$learner->last_name}" : 'Inconnu',
                    'paid' => $payments->where('status', PaymentStatus::Paye)->sum('amount'),
                    'pending' => $payments->whereIn('status', [PaymentStatus::EnAttente, PaymentStatus::EnRetard])->sum('amount'),
                ];
            })->values();

        return [
            'cohort' => $cohort->name,
            'campus_formation' => $cohort->campusFormation?->name,
            'total_expected' => $cohort->total_expected,
            'total_collected' => $cohort->total_collected,
            'total_remaining' => $cohort->total_remaining,
            'learners_with_overdue_payment' => $overdueCount,
            'per_learner' => $perLearner->all(),
        ];
    }

    private static function listExpenses(array $args): array
    {
        $limit = self::clampLimit($args);

        $query = Expense::with('formation:id,name');

        if (! empty($args['formation_id'])) {
            $query->where('formation_id', $args['formation_id']);
        }
        if (! empty($args['date_from'])) {
            $query->whereDate('expense_date', '>=', $args['date_from']);
        }
        if (! empty($args['date_to'])) {
            $query->whereDate('expense_date', '<=', $args['date_to']);
        }

        $expenses = $query->orderByDesc('expense_date')->get();
        $total = $expenses->sum('amount');
        $page = $expenses->take($limit);

        return [
            'total_matching' => $expenses->count(),
            'total_amount' => $total,
            'returned' => $page->count(),
            'expenses' => $page->map(fn ($e) => [
                'title' => $e->title,
                'amount' => $e->amount,
                'date' => $e->expense_date?->format('d/m/Y'),
                'formation' => $e->formation?->name,
                'spent_by' => $e->spent_by,
            ])->values()->all(),
        ];
    }

    private static function listFormationLinks(array $args): array
    {
        $links = FormationLink::where('formation_id', $args['formation_id'] ?? '')
            ->orderByDesc('created_at')
            ->get();

        return ['links' => $links->map(fn ($l) => [
            'title' => $l->title,
            'url' => $l->url,
        ])->all()];
    }

    private static function listMedia(array $args): array
    {
        $limit = self::clampLimit($args);

        $query = Media::where('formation_id', $args['formation_id'] ?? '');

        if (! empty($args['album'])) {
            $query->where('album', $args['album']);
        }

        $media = $query->orderByDesc('created_at')->get();
        $page = $media->take($limit);

        return [
            'total_matching' => $media->count(),
            'returned' => $page->count(),
            'media' => $page->map(fn ($m) => [
                'title' => $m->title,
                'type' => $m->type,
                'album' => $m->album,
                'size' => $m->formatted_size,
            ])->values()->all(),
        ];
    }

    private static function listEmails(array $args): array
    {
        $limit = self::clampLimit($args);

        $query = Email::query();

        if (! empty($args['direction'])) {
            $query->where('direction', $args['direction']);
        }
        if (array_key_exists('is_read', $args)) {
            $query->where('is_read', (bool) $args['is_read']);
        }
        if (! empty($args['thread_id'])) {
            $query->where('thread_id', $args['thread_id']);
        }

        $emails = $query->orderByDesc('created_at')->get();
        $page = $emails->take($limit);

        return [
            'total_matching' => $emails->count(),
            'returned' => $page->count(),
            'emails' => $page->map(fn ($e) => [
                'subject' => $e->subject,
                'from' => $e->from_email,
                'to' => $e->to,
                'direction' => $e->direction,
                'is_read' => $e->is_read,
                'date' => ($e->sent_at ?? $e->received_at)?->format('d/m/Y H:i'),
            ])->values()->all(),
        ];
    }

    private static function listWhatsappMessages(array $args): array
    {
        $limit = self::clampLimit($args);

        $query = WhatsAppMessage::with('learner:id,first_name,last_name');

        if (! empty($args['learner_id'])) {
            $query->where('learner_id', $args['learner_id']);
        }
        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $messages = $query->orderByDesc('created_at')->get();
        $page = $messages->take($limit);

        return [
            'total_matching' => $messages->count(),
            'returned' => $page->count(),
            'messages' => $page->map(fn ($m) => [
                'recipient' => $m->recipient_name ?? $m->learner?->first_name,
                'phone' => $m->phone,
                'status' => $m->status,
                'direction' => $m->direction,
                'formation' => $m->formation_name,
            ])->values()->all(),
        ];
    }

    private static function listReferenceData(array $args): array
    {
        $type = $args['type'] ?? '';

        return match ($type) {
            'education_levels' => ['education_levels' => EducationLevel::orderBy('order')->pluck('name')],
            'age_ranges' => ['age_ranges' => AgeRange::orderBy('order')->get(['name', 'age_min', 'age_max'])],
            'vulnerabilities' => ['vulnerabilities' => Vulnerability::orderBy('order')->pluck('name')],
            'last_diplomas' => ['last_diplomas' => LastDiploma::orderBy('order')->pluck('name')],
            'roles' => ['roles' => Role::pluck('name')],
            default => ['error' => "Type de référence inconnu : {$type}"],
        };
    }
}
