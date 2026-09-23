<?php

use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Fix password to original
$user = User::where('email', 'zounoupawiyo@gmail.com')->first();
if ($user) {
    $user->password = 'password';
    $user->save();
    echo "password ok\n";
}

$j = json_decode(file_get_contents(storage_path('inertia-devtools/01M37BW9D545HMNEE1VCVAWJMH.json')), true);
$p = $j['http']['responseBody']['value']['props'] ?? $j['propValues'];
$formations = $p['activeFormations']['data'] ?? [];
$enrollments = $p['recentEnrollments'] ?? [];

$parse = function (?string $d) {
    if (! $d) {
        return null;
    }
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $d, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    return $d;
};

foreach ($formations as $f) {
    $project = Project::firstOrCreate(
        ['name' => $f['project_name']],
        [
            'description' => 'Restauré dashboard Inertia (commit abandon/remboursement)',
            'started_at' => $parse($f['started_at']) ?? now()->toDateString(),
            'status' => 'active',
        ]
    );

    $model = Formation::withTrashed()->firstOrNew(['id' => $f['id']]);
    $model->forceFill([
        'id' => $f['id'],
        'project_id' => $project->id,
        'name' => $f['name'],
        'started_at' => $parse($f['started_at']) ?? now()->toDateString(),
        'ended_at' => $parse($f['ended_at'] ?? null),
        'status' => 'active',
        'deleted_at' => null,
    ]);
    $model->save();
    echo "OK formation {$f['name']} -> {$project->name}\n";
}

$webProject = Project::firstOrCreate(
    ['name' => 'atelier 2026'],
    ['started_at' => '2026-05-12', 'status' => 'active']
);
$web = Formation::where('name', 'Developpement web')->first();
if (! $web) {
    $web = new Formation;
    $web->forceFill([
        'id' => (string) Str::uuid(),
        'project_id' => $webProject->id,
        'name' => 'Developpement web',
        'started_at' => '2026-05-12',
        'status' => 'active',
    ]);
    $web->save();
}

foreach ($enrollments as $e) {
    if (! Learner::where('id', $e['id'])->exists()) {
        continue;
    }
    $formation = Formation::where('name', $e['formation_name'])->first() ?? $web;
    DB::table('formation_learner')->updateOrInsert(
        ['formation_id' => $formation->id, 'learner_id' => $e['id']],
        ['status' => 'in_progress', 'enrolled_at' => $e['enrolled_at'] ?? now()]
    );
    echo "enroll {$e['first_name']} {$e['last_name']} -> {$formation->name}\n";
}

echo 'projects='.Project::count()
    .' formations='.Formation::count()
    .' formation_learner='.DB::table('formation_learner')->count()
    .' learners='.Learner::count()
    .' payments='.DB::table('payments')->count()
    ."\n";
