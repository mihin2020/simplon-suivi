<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class PlanningNotifier
{
    /**
     * Notify newly assigned users about a planning item.
     *
     * @param  array<string>  $newAssigneeIds  IDs after sync
     * @param  array<string>  $previousAssigneeIds  IDs before sync
     * @param  string  $itemType  e.g. 'phase', 'activité', 'tâche'
     * @param  string  $itemName  Name/title of the item
     * @param  string  $itemId  UUID of the item
     * @param  User  $actor  The user performing the assignment
     */
    public function notifyNewAssignees(
        array $newAssigneeIds,
        array $previousAssigneeIds,
        string $itemType,
        string $itemName,
        string $itemId,
        User $actor,
        string $projectId,
    ): void {
        $added = array_diff($newAssigneeIds, $previousAssigneeIds);

        if (empty($added)) {
            return;
        }

        $actorName = trim($actor->first_name . ' ' . $actor->last_name);

        foreach ($added as $userId) {
            if ($userId === $actor->id) {
                continue;
            }

            Notification::create([
                'user_id' => $userId,
                'type' => 'planning_assignment',
                'title' => "Assignation à une {$itemType}",
                'message' => "{$actorName} vous a assigné à la {$itemType} « {$itemName} ».",
                'data' => [
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'actor_id' => $actor->id,
                    'route' => route('projects.show', $projectId),
                ],
            ]);
        }
    }
}
