<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Phase;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderController extends Controller
{
    public function phases(Request $request): JsonResponse
    {
        $ids = $this->orderedIds($request);

        $first = Phase::query()->findOrFail($ids[0]);
        $first->loadMissing('project');
        $this->authorize('update', $first->project);

        $this->persistOrder('phases', $ids, $first->project_id, 'project_id');

        return response()->json(['ok' => true]);
    }

    public function activities(Request $request): JsonResponse
    {
        $ids = $this->orderedIds($request);

        $first = Activity::query()->findOrFail($ids[0]);
        $first->loadMissing('phase.project');
        $this->authorize('update', $first->phase->project);

        $this->persistOrder('activities', $ids, $first->phase_id, 'phase_id');

        return response()->json(['ok' => true]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $ids = $this->orderedIds($request);

        $first = Task::query()->findOrFail($ids[0]);
        $first->loadMissing('activity.phase.project');
        $this->authorize('update', $first->activity->phase->project);

        $this->persistOrder('tasks', $ids, $first->activity_id, 'activity_id');

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<string>
     */
    private function orderedIds(Request $request): array
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['uuid'],
        ]);

        return array_values($validated['ordered_ids']);
    }

    /**
     * One UPDATE for the whole list instead of one query per row.
     */
    private function persistOrder(string $table, array $ids, string $parentId, string $parentColumn): void
    {
        $cases = [];
        $bindings = [];

        foreach ($ids as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $index;
        }

        $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings[] = now();
        $bindings[] = $parentId;
        $bindings = array_merge($bindings, $ids);

        DB::update(
            "UPDATE `{$table}` SET `position` = CASE `id` ".implode(' ', $cases)." END, `updated_at` = ? WHERE `{$parentColumn}` = ? AND `id` IN ({$inPlaceholders})",
            $bindings
        );
    }
}
