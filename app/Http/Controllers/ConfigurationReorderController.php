<?php

namespace App\Http\Controllers;

use App\Models\AgeRange;
use App\Models\ContractType;
use App\Models\EducationLevel;
use App\Models\LastDiploma;
use App\Models\TrainerProfile;
use App\Models\Vulnerability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConfigurationReorderController extends Controller
{
    private const TYPES = [
        'trainer-profiles' => [TrainerProfile::class, 'trainer_profiles'],
        'education-levels' => [EducationLevel::class, 'education_levels'],
        'age-ranges' => [AgeRange::class, 'age_ranges'],
        'vulnerabilities' => [Vulnerability::class, 'vulnerabilities'],
        'last-diplomas' => [LastDiploma::class, 'last_diplomas'],
        'contract-types' => [ContractType::class, 'contract_types'],
    ];

    public function __invoke(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('configuration.manage'), 403);
        abort_unless(isset(self::TYPES[$type]), 404);

        [$modelClass, $table] = self::TYPES[$type];
        $usesUuid = in_array('Illuminate\\Database\\Eloquent\\Concerns\\HasUuids', class_uses_recursive($modelClass), true);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => $usesUuid
                ? ['uuid', Rule::exists($table, 'id')]
                : ['integer', Rule::exists($table, 'id')],
        ]);

        $ids = array_values($validated['ordered_ids']);

        $cases = [];
        $bindings = [];
        foreach ($ids as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $index;
        }

        $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        DB::update(
            "UPDATE `{$table}` SET `order` = CASE `id` ".implode(' ', $cases)." END WHERE `id` IN ({$inPlaceholders})",
            $bindings
        );

        return response()->json(['ok' => true]);
    }
}
