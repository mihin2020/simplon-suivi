<?php

namespace Tests\Unit\Services;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProgressCalculator;
use Tests\TestCase;

class ProgressCalculatorTest extends TestCase
{
    private ProgressCalculator $calculator;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ProgressCalculator;
        $this->actor = $this->createSuperAdmin();
    }

    public function test_activity_without_tasks_is_zero(): void
    {
        $activity = Activity::factory()->create([
            'created_by' => $this->actor->id,
        ]);

        $this->assertSame(0, $this->calculator->forActivity($activity->load('tasks')));
    }

    public function test_activity_progress_is_completed_tasks_ratio(): void
    {
        $activity = Activity::factory()->create(['created_by' => $this->actor->id]);

        Task::factory()->count(2)->done()->create([
            'activity_id' => $activity->id,
            'created_by' => $this->actor->id,
        ]);
        Task::factory()->count(2)->create([
            'activity_id' => $activity->id,
            'created_by' => $this->actor->id,
            'status' => TaskStatus::Todo,
        ]);

        $this->assertSame(50, $this->calculator->forActivity($activity->load('tasks')));
    }

    public function test_phase_progress_is_average_of_activities(): void
    {
        $phase = Phase::factory()->create(['created_by' => $this->actor->id]);

        $full = Activity::factory()->create(['phase_id' => $phase->id, 'created_by' => $this->actor->id]);
        Task::factory()->done()->create(['activity_id' => $full->id, 'created_by' => $this->actor->id]);

        $half = Activity::factory()->create(['phase_id' => $phase->id, 'created_by' => $this->actor->id]);
        Task::factory()->done()->create(['activity_id' => $half->id, 'created_by' => $this->actor->id]);
        Task::factory()->create(['activity_id' => $half->id, 'created_by' => $this->actor->id]);

        $empty = Activity::factory()->create(['phase_id' => $phase->id, 'created_by' => $this->actor->id]);
        $this->assertNotNull($empty->id);

        $this->assertSame(50, $this->calculator->forPhase($phase->load('activities.tasks')));
    }

    public function test_project_progress_is_average_of_phases(): void
    {
        $project = Project::factory()->create();

        $donePhase = Phase::factory()->create(['project_id' => $project->id, 'created_by' => $this->actor->id]);
        $doneActivity = Activity::factory()->create(['phase_id' => $donePhase->id, 'created_by' => $this->actor->id]);
        Task::factory()->done()->create(['activity_id' => $doneActivity->id, 'created_by' => $this->actor->id]);

        $halfPhase = Phase::factory()->create(['project_id' => $project->id, 'created_by' => $this->actor->id]);
        $halfActivity = Activity::factory()->create(['phase_id' => $halfPhase->id, 'created_by' => $this->actor->id]);
        Task::factory()->done()->create(['activity_id' => $halfActivity->id, 'created_by' => $this->actor->id]);
        Task::factory()->create(['activity_id' => $halfActivity->id, 'created_by' => $this->actor->id]);

        $this->assertSame(75, $this->calculator->forProject($project->load('phases.activities.tasks')));
    }

    public function test_project_without_phases_is_zero(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(0, $this->calculator->forProject($project->load('phases')));
    }
}
