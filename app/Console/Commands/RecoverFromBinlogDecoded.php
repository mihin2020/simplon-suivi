<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoverFromBinlogDecoded extends Command
{
    protected $signature = 'app:recover-from-binlog
                            {--file= : Path to decoded mysqlbinlog file}
                            {--dry-run : Parse only}';

    protected $description = 'Recover rows from DECODE-ROWS mysqlbinlog output (INSERT/UPDATE after-images)';

    /** @var array<string, list<string>> */
    private array $columns = [];

    /** Tables safe to restore (order matters for FKs we can satisfy) */
    private array $priority = [
        'notifications',
        'forms',
        'form_fields',
        'form_responses',
        'form_answers',
        'phases',
        'activities',
        'tasks',
        'activity_user',
        'task_user',
        'phase_user',
        'learner_interviews',
        'formation_learner',
        'cohort_learner',
        // payments skipped: column order shifted after refund migration; restored via Inertia
        'learners',
    ];

    public function handle(): int
    {
        $file = $this->option('file') ?: storage_path('app/binlog_decoded_suivi.sql');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->loadColumnMaps();
        $this->info('Parsing '.$file.'…');

        $rowsByTable = [];
        $handle = fopen($file, 'r');
        $currentOp = null;
        $currentTable = null;
        $currentSection = null; // where|set
        $where = [];
        $set = [];

        $flush = function () use (&$currentOp, &$currentTable, &$where, &$set, &$rowsByTable) {
            if (! $currentOp || ! $currentTable || ! isset($this->columns[$currentTable])) {
                $currentOp = $currentTable = $currentSection = null;
                $where = $set = [];

                return;
            }

            $cols = $this->columns[$currentTable];
            $values = match ($currentOp) {
                'INSERT' => $set,
                'UPDATE' => $set !== [] ? $set : $where,
                'DELETE' => $where,
                default => [],
            };

            if ($values === [] || $currentOp === 'DELETE') {
                $currentOp = $currentTable = null;
                $where = $set = [];

                return;
            }

            $row = [];
            foreach ($values as $i => $val) {
                $col = $cols[$i] ?? null;
                if ($col === null) {
                    continue;
                }
                $row[$col] = $val;
            }

            if ($row === [] || empty($row[$cols[0] ?? 'id'] ?? null) && ! isset($row['id'])) {
                // formation_learner may use autoincrement id
            }

            $key = $row['id'] ?? (isset($row['cohort_id'], $row['learner_id'])
                ? $row['cohort_id'].'|'.$row['learner_id']
                : (isset($row['formation_id'], $row['learner_id'])
                    ? $row['formation_id'].'|'.$row['learner_id']
                    : md5(json_encode($row))));

            $rowsByTable[$currentTable][$key] = $row;

            $currentOp = $currentTable = null;
            $where = $set = [];
        };

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);

            if (preg_match('/^### (INSERT INTO|UPDATE|DELETE FROM) `suivi-laravel`\.`([^`]+)`/', $line, $m)) {
                $flush();
                $currentOp = match ($m[1]) {
                    'INSERT INTO' => 'INSERT',
                    'UPDATE' => 'UPDATE',
                    'DELETE FROM' => 'DELETE',
                };
                $currentTable = $m[2];
                $currentSection = null;
                $where = $set = [];

                continue;
            }

            if ($currentOp === null) {
                continue;
            }

            if ($line === '### WHERE') {
                $currentSection = 'where';

                continue;
            }
            if ($line === '### SET') {
                $currentSection = 'set';

                continue;
            }

            if (preg_match('/^###\s+@(\d+)=(.*)$/', $line, $m)) {
                $idx = ((int) $m[1]) - 1;
                $val = $this->parseValue(trim($m[2]));
                if ($currentSection === 'where') {
                    $where[$idx] = $val;
                } elseif ($currentSection === 'set') {
                    $set[$idx] = $val;
                }
            }
        }
        $flush();
        fclose($handle);

        foreach ($rowsByTable as $table => $rows) {
            $this->line(sprintf('  %-22s %d unique rows', $table, count($rows)));
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        // Collect project/formation stubs from forms/phases/formation_learner
        $projectIds = [];
        $formationIds = [];
        foreach (['forms', 'phases'] as $t) {
            foreach ($rowsByTable[$t] ?? [] as $row) {
                if (! empty($row['project_id'])) {
                    $projectIds[$row['project_id']] = true;
                }
                if (! empty($row['formation_id'])) {
                    $formationIds[$row['formation_id']] = true;
                }
            }
        }
        foreach ($rowsByTable['formation_learner'] ?? [] as $row) {
            if (! empty($row['formation_id'])) {
                $formationIds[$row['formation_id']] = true;
            }
        }

        // Enrich from dashboard snapshot names if present
        $dashboardFormations = $this->dashboardFormationHints();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $adminId = User::where('email', 'zounoupawiyo@gmail.com')->value('id')
                ?? User::query()->value('id');

            foreach (array_keys($projectIds) as $pid) {
                $hint = collect($dashboardFormations)->first(fn ($f) => ($f['project_guess_id'] ?? null) === $pid);
                $name = $hint['project_name'] ?? ('Projet récupéré '.substr($pid, 0, 8));
                Project::withTrashed()->firstOrNew(['id' => $pid])->forceFill([
                    'id' => $pid,
                    'name' => $name,
                    'description' => 'Restauré depuis binlog (stub)',
                    'started_at' => now()->toDateString(),
                    'status' => 'active',
                    'deleted_at' => null,
                ])->save();
            }

            // Also create projects mentioned on dashboard without id
            foreach ($dashboardFormations as $df) {
                if (empty($df['project_name'])) {
                    continue;
                }
                $existing = Project::where('name', $df['project_name'])->first();
                if (! $existing) {
                    // create with random uuid only if we have formation id to link later
                }
            }

            foreach ($dashboardFormations as $df) {
                if (empty($df['id'])) {
                    continue;
                }
                $formationIds[$df['id']] = true;
            }

            foreach (array_keys($formationIds) as $fid) {
                $hint = collect($dashboardFormations)->firstWhere('id', $fid);
                $projectName = $hint['project_name'] ?? null;
                $projectId = null;
                if ($projectName) {
                    $projectId = Project::where('name', $projectName)->value('id');
                    if (! $projectId) {
                        $projectId = (string) \Illuminate\Support\Str::uuid();
                        // Prefer ULID-like: use first formation's related if any form has it
                        foreach ($rowsByTable['forms'] ?? [] as $fr) {
                            if (($fr['formation_id'] ?? null) === $fid && ! empty($fr['project_id'])) {
                                $projectId = $fr['project_id'];
                                break;
                            }
                        }
                        Project::withTrashed()->firstOrNew(['id' => $projectId])->forceFill([
                            'id' => $projectId,
                            'name' => $projectName,
                            'description' => 'Restauré depuis dashboard Inertia',
                            'started_at' => now()->toDateString(),
                            'status' => 'active',
                            'deleted_at' => null,
                        ])->save();
                    }
                }
                if (! $projectId) {
                    $projectId = Project::query()->value('id');
                }
                if (! $projectId) {
                    $projectId = (string) \Illuminate\Support\Str::uuid();
                    Project::withTrashed()->firstOrNew(['id' => $projectId])->forceFill([
                        'id' => $projectId,
                        'name' => 'Projet récupéré',
                        'started_at' => now()->toDateString(),
                        'status' => 'active',
                    ])->save();
                }

                Formation::withTrashed()->firstOrNew(['id' => $fid])->forceFill([
                    'id' => $fid,
                    'project_id' => $projectId,
                    'name' => $hint['name'] ?? ('Formation '.substr($fid, 0, 8)),
                    'started_at' => $this->parseFrDate($hint['started_at'] ?? null) ?? now()->toDateString(),
                    'ended_at' => $this->parseFrDate($hint['ended_at'] ?? null),
                    'status' => 'active',
                    'deleted_at' => null,
                ])->save();
            }

            foreach ($this->priority as $table) {
                if (empty($rowsByTable[$table]) || ! Schema::hasTable($table)) {
                    continue;
                }
                $n = 0;
                foreach ($rowsByTable[$table] as $row) {
                    $row = $this->sanitizeRow($table, $row, $adminId);
                    if ($row === null) {
                        continue;
                    }

                    if ($table === 'cohort_learner' || ($table === 'formation_learner' && empty($row['id']))) {
                        $keys = $table === 'cohort_learner'
                            ? ['cohort_id' => $row['cohort_id'], 'learner_id' => $row['learner_id']]
                            : ['formation_id' => $row['formation_id'], 'learner_id' => $row['learner_id']];
                        DB::table($table)->updateOrInsert($keys, $row);
                    } elseif (! empty($row['id'])) {
                        $exists = DB::table($table)->where('id', $row['id'])->exists();
                        if ($exists) {
                            $id = $row['id'];
                            unset($row['id']);
                            DB::table($table)->where('id', $id)->update($row);
                        } else {
                            DB::table($table)->insert($row);
                        }
                    } else {
                        continue;
                    }
                    $n++;
                }
                $this->info("Restored {$n} rows into {$table}");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('Binlog recovery finished.');
        foreach (['projects', 'formations', 'forms', 'form_responses', 'form_answers', 'phases', 'activities', 'tasks', 'learner_interviews', 'formation_learner'] as $t) {
            if (Schema::hasTable($t)) {
                $this->line("  {$t}: ".DB::table($t)->count());
            }
        }

        return self::SUCCESS;
    }

    private function loadColumnMaps(): void
    {
        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_suivi-laravel';
        foreach ($tables as $t) {
            $name = $t->$key ?? array_values((array) $t)[0];
            $cols = DB::select("SHOW COLUMNS FROM `{$name}`");
            $this->columns[$name] = array_map(fn ($c) => $c->Field, $cols);
        }
    }

    private function parseValue(string $raw): mixed
    {
        if ($raw === 'NULL') {
            return null;
        }
        if ($raw === 'TRUE' || $raw === 'true') {
            return 1;
        }
        if ($raw === 'FALSE' || $raw === 'false') {
            return 0;
        }
        if (preg_match("/^'(.*)'$/s", $raw, $m)) {
            $str = stripcslashes($m[1]);
            // mysqlbinlog date format YYYY:MM:DD or YYYY:MM:DD HH:MM:SS
            if (preg_match('/^(\d{4}):(\d{2}):(\d{2})(?: (\d{2}):(\d{2}):(\d{2}))?$/', $str, $d)) {
                return isset($d[4])
                    ? "{$d[1]}-{$d[2]}-{$d[3]} {$d[4]}:{$d[5]}:{$d[6]}"
                    : "{$d[1]}-{$d[2]}-{$d[3]}";
            }

            return $str;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }

    private function sanitizeRow(string $table, array $row, ?string $adminId): ?array
    {
        $allowed = $this->columns[$table] ?? [];
        $row = array_intersect_key($row, array_flip($allowed));

        // Skip orphan FKs we cannot satisfy for campus-only tables already filled
        if ($table === 'payments') {
            if (empty($row['cohort_id']) || empty($row['learner_id'])) {
                return null;
            }
            if (! DB::table('cohorts')->where('id', $row['cohort_id'])->exists()) {
                return null;
            }
            if (! DB::table('learners')->where('id', $row['learner_id'])->exists()) {
                return null;
            }
            if (! empty($row['refunded_by']) && ! DB::table('users')->where('id', $row['refunded_by'])->exists()) {
                $row['refunded_by'] = $adminId;
            }
        }

        if ($table === 'forms') {
            if (! empty($row['project_id']) && ! DB::table('projects')->where('id', $row['project_id'])->exists()) {
                // ensure stub
                Project::withTrashed()->firstOrNew(['id' => $row['project_id']])->forceFill([
                    'id' => $row['project_id'],
                    'name' => 'Projet '.substr($row['project_id'], 0, 8),
                    'started_at' => now()->toDateString(),
                    'status' => 'active',
                ])->save();
            }
            if (! empty($row['formation_id']) && ! DB::table('formations')->where('id', $row['formation_id'])->exists()) {
                $pid = $row['project_id'] ?? Project::query()->value('id');
                Formation::withTrashed()->firstOrNew(['id' => $row['formation_id']])->forceFill([
                    'id' => $row['formation_id'],
                    'project_id' => $pid,
                    'name' => 'Formation '.substr($row['formation_id'], 0, 8),
                    'started_at' => now()->toDateString(),
                    'status' => 'active',
                ])->save();
            }
            if (! empty($row['created_by']) && ! DB::table('users')->where('id', $row['created_by'])->exists()) {
                $row['created_by'] = $adminId;
            }
        }

        if (in_array($table, ['phases', 'activities', 'tasks'], true)) {
            if (! empty($row['created_by']) && ! DB::table('users')->where('id', $row['created_by'])->exists()) {
                $row['created_by'] = $adminId;
            }
        }

        if ($table === 'phases' && ! empty($row['project_id']) && ! DB::table('projects')->where('id', $row['project_id'])->exists()) {
            Project::withTrashed()->firstOrNew(['id' => $row['project_id']])->forceFill([
                'id' => $row['project_id'],
                'name' => 'Projet '.substr($row['project_id'], 0, 8),
                'started_at' => now()->toDateString(),
                'status' => 'active',
            ])->save();
        }

        if ($table === 'formation_learner') {
            if (empty($row['formation_id']) || empty($row['learner_id'])) {
                return null;
            }
            if (! DB::table('formations')->where('id', $row['formation_id'])->exists()) {
                return null;
            }
            if (! DB::table('learners')->where('id', $row['learner_id'])->exists()) {
                return null;
            }
        }

        if ($table === 'form_responses' && ! empty($row['reviewed_by']) && ! DB::table('users')->where('id', $row['reviewed_by'])->exists()) {
            $row['reviewed_by'] = $adminId;
        }

        if ($table === 'notifications' && ! empty($row['user_id']) && ! DB::table('users')->where('id', $row['user_id'])->exists()) {
            $row['user_id'] = $adminId;
        }

        // Convert unix timestamps stored as ints in binlog for datetime columns
        foreach ($row as $col => $val) {
            if ($val === null || ! is_numeric($val)) {
                continue;
            }
            if (str_ends_with($col, '_at') || in_array($col, ['due_date', 'paid_at', 'enrolled_at', 'withdrawn_at', 'completed_at', 'published_at', 'closed_at', 'locked_at', 'conducted_at', 'next_follow_up_at', 'submitted_at', 'reviewed_at'], true)) {
                $int = (int) $val;
                if ($int > 1_000_000_000 && $int < 2_000_000_000) {
                    $row[$col] = date('Y-m-d H:i:s', $int);
                }
            }
        }

        return $row;
    }

    private function dashboardFormationHints(): array
    {
        $dir = storage_path('inertia-devtools');
        $file = $dir.'/01M37BW9D545HMNEE1VCVAWJMH.json';
        if (! is_file($file)) {
            return [];
        }
        $json = json_decode(file_get_contents($file), true);
        $data = $json['http']['responseBody']['value']['props']['activeFormations']['data']
            ?? $json['propValues']['activeFormations']['data']
            ?? [];

        return is_array($data) ? $data : [];
    }

    private function parseFrDate(?string $d): ?string
    {
        if (! $d) {
            return null;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $d, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }
}
