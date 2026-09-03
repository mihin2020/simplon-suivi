<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Phase;
use App\Models\Project;
use Illuminate\Support\Collection;

class ProgressCalculator
{
    public function forActivity(Activity $activity): int
    {
        $tasks = $activity->relationLoaded('tasks')
            ? $activity->tasks
            : $activity->tasks()->get();

        if ($tasks->isEmpty()) {
            return 0;
        }

        $completed = $tasks->filter(fn ($task) => $task->isCompleted())->count();

        return (int) round(($completed / $tasks->count()) * 100);
    }

    public function forPhase(Phase $phase): int
    {
        $activities = $phase->relationLoaded('activities')
            ? $phase->activities
            : $phase->activities()->with('tasks')->get();

        return $this->weightedAverage($activities, fn (Activity $activity) => $this->forActivity($activity));
    }

    public function forProject(Project $project): int
    {
        $phases = $project->relationLoaded('phases')
            ? $project->phases
            : $project->phases()->with('activities.tasks')->get();

        return $this->weightedAverage($phases, fn (Phase $phase) => $this->forPhase($phase));
    }

    /**
     * Hydrate progress_percentage on the project and its nested planning tree.
     */
    public function hydrate(Project $project): Project
    {
        $project->loadMissing([
            'phases' => fn ($query) => $query->ordered()->select([
                'id', 'project_id', 'created_by', 'name', 'description',
                'started_at', 'ended_at', 'position', 'weight', 'priority',
            ]),
            'phases.assignees:id,first_name,last_name,email',
            'phases.creator:id,first_name,last_name',
            'phases.activities' => fn ($query) => $query->ordered()->select([
                'id', 'phase_id', 'created_by', 'title', 'description',
                'started_at', 'ended_at', 'resources', 'position', 'weight', 'priority',
            ]),
            'phases.activities.assignees:id,first_name,last_name,email',
            'phases.activities.creator:id,first_name,last_name',
            'phases.activities.tasks' => fn ($query) => $query->ordered()->select([
                'id', 'activity_id', 'created_by', 'title', 'description',
                'started_at', 'ended_at', 'status', 'position', 'priority',
            ]),
            'phases.activities.tasks.assignees:id,first_name,last_name,email',
            'phases.activities.tasks.creator:id,first_name,last_name',
        ]);

        foreach ($project->phases as $phase) {
            foreach ($phase->activities as $activity) {
                $activity->setAttribute('progress_percentage', $this->forActivity($activity));
            }

            $phase->setAttribute('progress_percentage', $this->forPhase($phase));
        }

        $project->setAttribute('progress_percentage', $this->forProject($project));

        return $project;
    }

    /**
     * Simple average today (all weights default to 1).
     * Using weights keeps a future ponderation change isolated here.
     *
     * @param  Collection<int, Phase|Activity>  $items
     * @param  callable(Phase|Activity): int  $progress
     */
    private function weightedAverage(Collection $items, callable $progress): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $totalWeight = $items->sum(fn ($item) => max(1, (int) ($item->weight ?? 1)));

        if ($totalWeight === 0) {
            return 0;
        }

        $weighted = $items->sum(
            fn ($item) => $progress($item) * max(1, (int) ($item->weight ?? 1))
        );

        return (int) round($weighted / $totalWeight);
    }
}
