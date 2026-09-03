<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use Tests\TestCase;

class ProjectPlanningTest extends TestCase
{
    public function test_super_admin_can_create_a_phase_assigned_to_creator_by_default(): void
    {
        $user = $this->createSuperAdmin();
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->post("/projects/{$project->id}/phases", [
                'name' => 'Préparation et mobilisation',
                'description' => 'Phase initiale',
                'started_at' => '2026-09-15',
                'ended_at' => '2026-09-30',
            ])
            ->assertRedirect();

        $phase = Phase::query()->first();

        $this->assertNotNull($phase);
        $this->assertSame('Préparation et mobilisation', $phase->name);
        $this->assertSame($user->id, $phase->created_by);
        $this->assertTrue($phase->assignees->contains('id', $user->id));
    }

    public function test_trainer_cannot_create_a_phase(): void
    {
        $project = Project::factory()->create();

        $this->actingAsTrainer()
            ->post("/projects/{$project->id}/phases", [
                'name' => 'Interdite',
                'started_at' => '2026-09-15',
                'ended_at' => '2026-09-30',
            ])
            ->assertForbidden();
    }

    public function test_phase_dates_must_stay_inside_the_project(): void
    {
        $project = Project::factory()->create([
            'started_at' => '2026-09-01',
            'ended_at' => '2026-09-30',
        ]);

        $this->actingAsSuperAdmin()
            ->from("/projects/{$project->id}")
            ->post("/projects/{$project->id}/phases", [
                'name' => 'Hors période',
                'started_at' => '2026-08-01',
                'ended_at' => '2026-10-15',
            ])
            ->assertSessionHasErrors(['started_at', 'ended_at']);
    }

    public function test_inactive_or_trainer_users_cannot_be_assigned(): void
    {
        $actor = $this->createSuperAdmin();
        $project = Project::factory()->create();
        $trainer = $this->createTrainer();
        $inactiveAdmin = $this->createAdmin(false);

        $this->actingAs($actor)
            ->from("/projects/{$project->id}")
            ->post("/projects/{$project->id}/phases", [
                'name' => 'Assignation invalide',
                'started_at' => '2026-09-15',
                'ended_at' => '2026-09-30',
                'assignee_ids' => [$trainer->id, $inactiveAdmin->id],
            ])
            ->assertSessionHasErrors(['assignee_ids.0', 'assignee_ids.1']);
    }

    public function test_activity_and_task_can_be_created_then_task_status_updated(): void
    {
        $user = $this->createSuperAdmin();
        $admin = $this->createAdmin();
        $phase = Phase::factory()->create(['created_by' => $user->id]);
        $phase->assignees()->attach($user->id);

        $this->actingAs($user)
            ->post("/phases/{$phase->id}/activities", [
                'title' => 'Préparer la formation',
                'description' => 'Supports et logistique',
                'started_at' => '2026-09-16',
                'ended_at' => '2026-09-25',
                'resources' => ['Ordinateur', 'Salle de formation'],
                'assignee_ids' => [$admin->id],
            ])
            ->assertRedirect();

        $activity = Activity::query()->first();
        $this->assertSame(['Ordinateur', 'Salle de formation'], $activity->resources);
        $this->assertTrue($activity->assignees->contains('id', $admin->id));

        $this->actingAs($user)
            ->post("/activities/{$activity->id}/tasks", [
                'title' => 'Préparer les supports',
                'started_at' => '2026-09-16',
                'ended_at' => '2026-09-18',
            ])
            ->assertRedirect();

        $task = Task::query()->first();
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertTrue($task->assignees->contains('id', $user->id));

        $this->actingAs($user)
            ->patch("/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value])
            ->assertRedirect();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_assigned_admin_can_update_task_status_without_project_update_permission(): void
    {
        $owner = $this->createSuperAdmin();
        $assignee = $this->createAdmin();
        $phase = Phase::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create([
            'phase_id' => $phase->id,
            'created_by' => $owner->id,
        ]);
        $task = Task::factory()->create([
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
            'status' => TaskStatus::Todo,
        ]);
        $task->assignees()->attach($assignee->id);

        $this->actingAs($assignee)
            ->patch("/tasks/{$task->id}/status", ['status' => TaskStatus::Done->value])
            ->assertRedirect();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);

        $this->actingAs($assignee)
            ->put("/phases/{$phase->id}", [
                'name' => 'Tentative',
                'started_at' => $phase->started_at->toDateString(),
                'ended_at' => $phase->ended_at->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_deleting_a_phase_soft_deletes_its_children(): void
    {
        $user = $this->createSuperAdmin();
        $phase = Phase::factory()->create(['created_by' => $user->id]);
        $activity = Activity::factory()->create([
            'phase_id' => $phase->id,
            'created_by' => $user->id,
        ]);
        $task = Task::factory()->create([
            'activity_id' => $activity->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete("/phases/{$phase->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($phase);
        $this->assertSoftDeleted($activity);
        $this->assertSoftDeleted($task);
    }

    public function test_show_page_includes_progress_and_assignable_admins_only(): void
    {
        $user = $this->createSuperAdmin();
        $admin = $this->createAdmin();
        $trainer = $this->createTrainer();
        $inactive = $this->createAdmin(false);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->where('project.progress_percentage', 0)
                ->has('assignableUsers', 2)
            );

        $this->assertDatabaseHas('users', ['id' => $trainer->id]);
        $this->assertDatabaseHas('users', ['id' => $inactive->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
