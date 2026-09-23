<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RecoverFromInertiaDevtools extends Command
{
    protected $signature = 'app:recover-from-inertia-devtools
                            {--dry-run : Parse only, do not write}
                            {--password=password : Password for recovered super admin}';

    protected $description = 'Recover campus/learner/payment data from Inertia DevTools snapshots (pre db:wipe)';

    public function handle(): int
    {
        $dir = storage_path('inertia-devtools');
        $wipeAt = strtotime('2026-09-23 16:55:00');

        $users = [];
        $formations = [];
        $cohorts = [];
        $learners = [];
        $pivots = []; // key: cohort_id|learner_id
        $payments = []; // keyed by payment id, keep latest by updated_at
        $educationLevels = [];

        $files = collect(glob($dir.'/*.json'))
            ->filter(fn ($f) => basename($f) !== '_meta.json')
            ->filter(fn ($f) => filemtime($f) < $wipeAt)
            ->sortBy(fn ($f) => filemtime($f));

        $this->info('Parsing '.$files->count().' snapshots…');

        foreach ($files as $file) {
            $raw = json_decode(file_get_contents($file), true);
            if (! is_array($raw)) {
                continue;
            }

            $props = $raw['http']['responseBody']['value']['props']
                ?? $raw['propValues']
                ?? null;

            if (! is_array($props)) {
                continue;
            }

            if (isset($props['auth']['user']['id'])) {
                $u = $props['auth']['user'];
                $nameParts = preg_split('/\s+/', trim($u['full_name'] ?? 'Admin'), 2);
                $users[$u['id']] = [
                    'id' => $u['id'],
                    'first_name' => $nameParts[0] ?? 'Admin',
                    'last_name' => $nameParts[1] ?? 'User',
                    'email' => $u['email'],
                    'role' => $u['role'] ?? UserRole::SuperAdmin->value,
                    'is_active' => true,
                ];
            }

            if (! empty($props['educationLevels']) && is_array($props['educationLevels'])) {
                foreach ($props['educationLevels'] as $el) {
                    if (! empty($el['id'])) {
                        $educationLevels[(int) $el['id']] = $el;
                    }
                }
            }

            // Campus formation from cohort.campus_formation
            if (! empty($props['cohort']['campus_formation']['id'])) {
                $f = $props['cohort']['campus_formation'];
                $this->mergeFormation($formations, $f);
            }

            if (! empty($props['cohort']['id'])) {
                $c = $props['cohort'];
                $this->mergeCohort($cohorts, $c);
                if (! empty($c['campus_formation']['id'])) {
                    $this->mergeFormation($formations, $c['campus_formation']);
                }
            }

            // Cohorts index
            if (! empty($props['cohorts']['data']) && is_array($props['cohorts']['data'])) {
                foreach ($props['cohorts']['data'] as $c) {
                    $this->mergeCohort($cohorts, $c);
                    if (! empty($c['campus_formation']['id'])) {
                        $this->mergeFormation($formations, $c['campus_formation']);
                    }
                }
            }

            // Flat cohorts list (some pages)
            if (! empty($props['cohorts']) && is_array($props['cohorts']) && empty($props['cohorts']['data'])) {
                foreach ($props['cohorts'] as $c) {
                    if (! is_array($c) || empty($c['id'])) {
                        continue;
                    }
                    $this->mergeCohort($cohorts, $c);
                    if (! empty($c['campus_formation']['id'])) {
                        $this->mergeFormation($formations, $c['campus_formation']);
                    }
                }
            }

            if (! empty($props['formations']) && is_array($props['formations'])) {
                foreach ($props['formations'] as $f) {
                    if (! empty($f['id'])) {
                    $this->mergeFormation($formations, $f);
                }
                }
            }

            if (! empty($props['byFormation']) && is_array($props['byFormation'])) {
                foreach ($props['byFormation'] as $row) {
                    // Shape A: { id, name, cohorts: [...] }
                    if (! empty($row['id']) && ! empty($row['name']) && empty($row['formation'])) {
                        $formations[$row['id']] = $this->formationAttrs([
                            'id' => $row['id'],
                            'name' => $row['name'],
                            'total_cost' => $row['cohorts'][0]['unit_cost'] ?? ($formations[$row['id']]['total_cost'] ?? 0),
                            'duration_months' => $formations[$row['id']]['duration_months'] ?? 1,
                            'mode' => $formations[$row['id']]['mode'] ?? 'presentiel',
                            'is_active' => true,
                        ]);
                        if (! empty($row['cohorts']) && is_array($row['cohorts'])) {
                            foreach ($row['cohorts'] as $c) {
                                if (! empty($c['id'])) {
                                    $c['campus_formation_id'] = $row['id'];
                                    $this->mergeCohort($cohorts, $c);
                                }
                            }
                        }
                    }

                    // Shape B: { formation: {...}, cohorts: [...] }
                    if (! empty($row['formation']['id'])) {
                        $this->mergeFormation($formations, $row['formation']);
                        if (! empty($row['cohorts']) && is_array($row['cohorts'])) {
                            foreach ($row['cohorts'] as $c) {
                                if (! empty($c['id'])) {
                                    if (empty($c['campus_formation_id'])) {
                                        $c['campus_formation_id'] = $row['formation']['id'];
                                    }
                                    $this->mergeCohort($cohorts, $c);
                                }
                            }
                        }
                    }
                }
            }

            // Learners from paginated cohort show
            if (! empty($props['learners']['data']) && is_array($props['learners']['data'])) {
                foreach ($props['learners']['data'] as $l) {
                    $this->mergeLearner($learners, $l);
                    if (! empty($l['pivot']['cohort_id'])) {
                        $this->mergePivot($pivots, $l['pivot'], $l['id']);
                    }
                }
            }

            if (! empty($props['availableLearners']) && is_array($props['availableLearners'])) {
                foreach ($props['availableLearners'] as $l) {
                    $this->mergeLearner($learners, $l);
                }
            }

            // Payments page — richest source
            if (! empty($props['learnerPayments']) && is_array($props['learnerPayments'])) {
                foreach ($props['learnerPayments'] as $row) {
                    if (! empty($row['learner']['id'])) {
                        $this->mergeLearner($learners, $row['learner']);
                        if (! empty($row['learner']['pivot'])) {
                            $pivot = $row['learner']['pivot'];
                            if (! empty($row['cohort_status'])) {
                                $pivot['status'] = $row['cohort_status'];
                            }
                            if (! empty($row['is_abandoned']) && empty($pivot['status'])) {
                                $pivot['status'] = 'retrait';
                            }
                            $this->mergePivot($pivots, $pivot, $row['learner']['id']);
                        } elseif (! empty($props['cohort']['id'])) {
                            $this->mergePivot($pivots, [
                                'cohort_id' => $props['cohort']['id'],
                                'learner_id' => $row['learner']['id'],
                                'status' => $row['cohort_status'] ?? 'actif',
                                'enrolled_at' => now()->toDateTimeString(),
                            ], $row['learner']['id']);
                        }
                    }

                    if (! empty($row['payments']) && is_array($row['payments'])) {
                        foreach ($row['payments'] as $p) {
                            if (empty($p['id'])) {
                                continue;
                            }
                            $prev = $payments[$p['id']] ?? null;
                            $prevTs = $prev['updated_at'] ?? '';
                            $newTs = $p['updated_at'] ?? '';
                            if ($prev === null || strcmp((string) $newTs, (string) $prevTs) >= 0) {
                                $payments[$p['id']] = $p;
                            }
                        }
                    }
                }
            }

            // availableCohorts snippets
            if (! empty($props['availableCohorts']) && is_array($props['availableCohorts'])) {
                foreach ($props['availableCohorts'] as $c) {
                    if (empty($c['id'])) {
                        continue;
                    }
                    $attrs = [
                        'id' => $c['id'],
                        'name' => $c['name'] ?? 'Cohorte',
                        'campus_formation_id' => $c['campus_formation_id'] ?? null,
                        'started_at' => null,
                        'ended_at' => null,
                        'capacity' => 30,
                        'status' => 'planifiee',
                        '_formation_name' => $c['formation_name'] ?? null,
                        '_total_cost' => $c['total_cost'] ?? null,
                    ];
                    $prev = $cohorts[$c['id']] ?? [];
                    $cohorts[$c['id']] = array_merge($attrs, array_filter($prev, fn ($v) => $v !== null));
                }
            }
        }

        // Resolve orphan cohorts that only have formation_name / create missing formations from name+cost
        foreach ($cohorts as $id => $c) {
            if (empty($c['campus_formation_id'])) {
                $fname = $c['_formation_name'] ?? null;
                if ($fname) {
                    foreach ($formations as $fid => $f) {
                        if (strcasecmp((string) ($f['name'] ?? ''), (string) $fname) === 0) {
                            $cohorts[$id]['campus_formation_id'] = $fid;
                            break;
                        }
                    }
                }
            }
            unset($cohorts[$id]['_formation_name'], $cohorts[$id]['_total_cost']);
        }

        // Drop cohorts still without formation (cannot insert)
        $cohorts = array_filter($cohorts, function ($c) {
            return ! empty($c['campus_formation_id']);
        });

        $this->table(
            ['Entity', 'Count'],
            [
                ['users', count($users)],
                ['formations', count($formations)],
                ['cohorts', count($cohorts)],
                ['learners', count($learners)],
                ['pivots', count($pivots)],
                ['payments', count($payments)],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — nothing written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($users, $formations, $cohorts, $learners, $pivots, $payments) {
            // Prefer recovered super admin over fresh seed
            foreach ($users as $u) {
                $model = User::withTrashed()->firstOrNew(['id' => $u['id']]);
                $model->forceFill([
                    'id' => $u['id'],
                    'first_name' => $u['first_name'],
                    'last_name' => $u['last_name'],
                    'email' => $u['email'],
                    'password' => $this->option('password'),
                    'role' => $u['role'],
                    'is_active' => true,
                    'deleted_at' => null,
                ]);
                $model->save();
                $this->line("User restored: {$u['email']} ({$u['id']})");
            }

            User::where('email', 'superadmin@simplon.bf')
                ->whereNotIn('id', array_keys($users))
                ->delete();

            foreach ($formations as $f) {
                $model = CampusFormation::withTrashed()->firstOrNew(['id' => $f['id']]);
                $model->forceFill([
                    'id' => $f['id'],
                    'name' => $f['name'],
                    'slug' => $f['slug'] ?? \Illuminate\Support\Str::slug($f['name']),
                    'description' => $f['description'] ?? null,
                    'duration_months' => $f['duration_months'] ?? 1,
                    'mode' => $f['mode'] ?? 'presentiel',
                    'total_cost' => $f['total_cost'] ?? 0,
                    'is_active' => $f['is_active'] ?? true,
                    'deleted_at' => $this->parseDate($f['deleted_at'] ?? null),
                    'created_at' => $this->parseDate($f['created_at'] ?? null) ?? now(),
                    'updated_at' => $this->parseDate($f['updated_at'] ?? null) ?? now(),
                ]);
                $model->save();
            }

            foreach ($cohorts as $c) {
                if (empty($c['campus_formation_id'])) {
                    $this->warn("Skip cohort {$c['id']} — missing formation");

                    continue;
                }
                if (! CampusFormation::withTrashed()->where('id', $c['campus_formation_id'])->exists()) {
                    $this->warn("Skip cohort {$c['id']} — formation {$c['campus_formation_id']} not found");

                    continue;
                }
                $model = Cohort::withTrashed()->firstOrNew(['id' => $c['id']]);
                $model->forceFill([
                    'id' => $c['id'],
                    'campus_formation_id' => $c['campus_formation_id'],
                    'name' => $c['name'],
                    'started_at' => $this->parseDate($c['started_at'] ?? null)?->toDateString(),
                    'ended_at' => $this->parseDate($c['ended_at'] ?? null)?->toDateString(),
                    'capacity' => $c['capacity'] ?? 30,
                    'status' => $c['status'] ?? 'planifiee',
                    'deleted_at' => null,
                    'created_at' => $this->parseDate($c['created_at'] ?? null) ?? now(),
                    'updated_at' => $this->parseDate($c['updated_at'] ?? null) ?? now(),
                ]);
                $model->save();
            }

            foreach ($learners as $l) {
                $attrs = [
                    'first_name' => $l['first_name'] ?? 'Inconnu',
                    'last_name' => $l['last_name'] ?? 'Inconnu',
                    'email' => $l['email'] ?? null,
                    'phone' => $l['phone'] ?? null,
                    'birth_date' => $this->parseDate($l['birth_date'] ?? null)?->toDateString(),
                    'birth_place' => $l['birth_place'] ?? null,
                    'gender' => $l['gender'] ?? null,
                    'education_level_id' => $l['education_level_id'] ?? null,
                    'photo_path' => $l['photo_path'] ?? null,
                    'photo_original_name' => $l['photo_original_name'] ?? null,
                    'cnib_path' => $l['cnib_path'] ?? null,
                    'cnib_original_name' => $l['cnib_original_name'] ?? null,
                    'cnib_number' => $l['cnib_number'] ?? null,
                    'marital_status' => $l['marital_status'] ?? null,
                    'children_count' => $l['children_count'] ?? 0,
                    'talent' => $l['talent'] ?? null,
                    'emergency_contact_name' => $l['emergency_contact_name'] ?? null,
                    'emergency_contact_firstname' => $l['emergency_contact_firstname'] ?? null,
                    'emergency_contact_phone' => $l['emergency_contact_phone'] ?? null,
                    'address' => $l['address'] ?? null,
                    'location' => $l['location'] ?? null,
                    'profile' => $l['profile'] ?? null,
                    'organization' => $l['organization'] ?? null,
                    'study_field' => $l['study_field'] ?? null,
                    'age_range_id' => $l['age_range_id'] ?? null,
                    'vulnerability_id' => $l['vulnerability_id'] ?? null,
                    'last_diploma_id' => $l['last_diploma_id'] ?? null,
                    'cv_path' => $l['cv_path'] ?? null,
                    'cv_original_name' => $l['cv_original_name'] ?? null,
                    'deleted_at' => null,
                    'created_at' => $this->parseDate($l['created_at'] ?? null),
                    'updated_at' => $this->parseDate($l['updated_at'] ?? null),
                ];

                // Prefer richer records (more non-null fields)
                $existing = Learner::withTrashed()->find($l['id']);
                if ($existing) {
                    $existing->fill(array_filter($attrs, fn ($v) => $v !== null));
                    $existing->deleted_at = null;
                    $existing->save();
                } else {
                    $learner = new Learner;
                    $learner->forceFill(array_merge($attrs, ['id' => $l['id']]));
                    $learner->save();
                }
            }

            foreach ($pivots as $pivot) {
                if (empty($pivot['cohort_id']) || empty($pivot['learner_id'])) {
                    continue;
                }
                if (! Cohort::where('id', $pivot['cohort_id'])->exists()) {
                    continue;
                }
                if (! Learner::where('id', $pivot['learner_id'])->exists()) {
                    continue;
                }

                DB::table('cohort_learner')->updateOrInsert(
                    [
                        'cohort_id' => $pivot['cohort_id'],
                        'learner_id' => $pivot['learner_id'],
                    ],
                    [
                        'status' => $pivot['status'] ?? 'actif',
                        'enrolled_at' => $pivot['enrolled_at'] ?? now(),
                        'abandon_motif' => $pivot['abandon_motif'] ?? null,
                        'abandoned_at' => $pivot['abandoned_at'] ?? null,
                    ]
                );
            }

            foreach ($payments as $p) {
                if (empty($p['cohort_id']) || empty($p['learner_id'])) {
                    continue;
                }
                if (! Cohort::where('id', $p['cohort_id'])->exists()) {
                    continue;
                }
                if (! Learner::where('id', $p['learner_id'])->exists()) {
                    continue;
                }

                $payment = Payment::firstOrNew(['id' => $p['id']]);
                $payment->forceFill([
                    'id' => $p['id'],
                    'cohort_id' => $p['cohort_id'],
                    'learner_id' => $p['learner_id'],
                    'amount' => (int) $p['amount'],
                    'installment_number' => $p['installment_number'] ?? 1,
                    'due_date' => $this->parseDate($p['due_date'] ?? null)?->toDateString(),
                    'paid_at' => $this->parseDate($p['paid_at'] ?? null)?->toDateString(),
                    'status' => $p['status'] ?? 'en_attente',
                    'payment_method' => $p['payment_method'] ?? null,
                    'reference' => $p['reference'] ?? null,
                    'notes' => $p['notes'] ?? null,
                    'refunded_at' => $this->parseDate($p['refunded_at'] ?? null),
                    'refund_amount' => $p['refund_amount'] ?? null,
                    'refund_motif' => $p['refund_motif'] ?? null,
                    'refunded_by' => (! empty($p['refunded_by']) && User::where('id', $p['refunded_by'])->exists())
                        ? $p['refunded_by']
                        : null,
                    'created_at' => $this->parseDate($p['created_at'] ?? null) ?? now(),
                    'updated_at' => $this->parseDate($p['updated_at'] ?? null) ?? now(),
                ]);
                $payment->save();
            }
        });

        $this->newLine();
        $this->info('Recovery done.');
        $this->line('Counts now:');
        $this->line('  learners: '.Learner::count());
        $this->line('  cohorts: '.Cohort::count());
        $this->line('  formations: '.CampusFormation::count());
        $this->line('  payments: '.Payment::count());
        $this->line('  pivots: '.DB::table('cohort_learner')->count());
        $this->newLine();
        $this->warn('Compte restauré : mot de passe = option --password (défaut: password).');

        return self::SUCCESS;
    }

    private function formationAttrs(array $f): array
    {
        return [
            'id' => $f['id'],
            'name' => $f['name'] ?? 'Formation',
            'slug' => $f['slug'] ?? null,
            'description' => $f['description'] ?? null,
            'duration_months' => $f['duration_months'] ?? null,
            'mode' => $f['mode'] ?? null,
            'total_cost' => $f['total_cost'] ?? null,
            'is_active' => $f['is_active'] ?? null,
            'deleted_at' => $f['deleted_at'] ?? null,
            'created_at' => $f['created_at'] ?? null,
            'updated_at' => $f['updated_at'] ?? null,
        ];
    }

    private function mergeFormation(array &$formations, array $f): void
    {
        if (empty($f['id'])) {
            return;
        }
        $attrs = $this->formationAttrs($f);
        $prev = $formations[$f['id']] ?? [];
        foreach ($attrs as $k => $v) {
            if ($v !== null && $v !== '') {
                $prev[$k] = $v;
            }
        }
        // Defaults when still missing
        $prev['name'] = $prev['name'] ?? 'Formation';
        $prev['duration_months'] = $prev['duration_months'] ?? 1;
        $prev['mode'] = $prev['mode'] ?? 'presentiel';
        $prev['total_cost'] = $prev['total_cost'] ?? 0;
        $prev['is_active'] = $prev['is_active'] ?? true;
        $formations[$f['id']] = $prev;
    }

    private function cohortAttrs(array $c): array
    {
        return [
            'id' => $c['id'],
            'campus_formation_id' => $c['campus_formation_id'] ?? ($c['campus_formation']['id'] ?? null),
            'name' => $c['name'] ?? 'Cohorte',
            'started_at' => $c['started_at'] ?? null,
            'ended_at' => $c['ended_at'] ?? null,
            'capacity' => $c['capacity'] ?? 30,
            'status' => $c['status'] ?? 'planifiee',
            'created_at' => $c['created_at'] ?? null,
            'updated_at' => $c['updated_at'] ?? null,
        ];
    }

    private function mergeCohort(array &$cohorts, array $c): void
    {
        if (empty($c['id'])) {
            return;
        }
        $attrs = $this->cohortAttrs($c);
        $prev = $cohorts[$c['id']] ?? [];
        foreach ($attrs as $k => $v) {
            if ($v !== null && $v !== '') {
                $prev[$k] = $v;
            }
        }
        $cohorts[$c['id']] = $prev;
    }

    private function mergeLearner(array &$learners, array $l): void
    {
        if (empty($l['id'])) {
            return;
        }
        $prev = $learners[$l['id']] ?? [];
        // Keep previous non-null values when new ones are null
        foreach ($l as $k => $v) {
            if ($v !== null && $k !== 'pivot' && $k !== 'education_level') {
                $prev[$k] = $v;
            }
        }
        $learners[$l['id']] = $prev;
    }

    private function mergePivot(array &$pivots, array $pivot, string $learnerId): void
    {
        $cohortId = $pivot['cohort_id'] ?? null;
        if (! $cohortId) {
            return;
        }
        $key = $cohortId.'|'.$learnerId;
        $pivot['learner_id'] = $learnerId;
        $prev = $pivots[$key] ?? [];
        $pivots[$key] = array_merge($prev, array_filter($pivot, fn ($v) => $v !== null));
    }

    private function parseDate(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
