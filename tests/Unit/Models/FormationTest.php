<?php

namespace Tests\Unit\Models;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use Tests\TestCase;

class FormationTest extends TestCase
{
    public function test_formation_belongs_to_project()
    {
        $project = Project::factory()->create();
        $formation = Formation::factory()->create(['project_id' => $project->id]);
        
        $this->assertInstanceOf(Project::class, $formation->project);
        $this->assertEquals($project->id, $formation->project->id);
    }

    public function test_formation_belongs_to_many_learners()
    {
        $formation = Formation::factory()->create();
        $learner = Learner::factory()->create();
        
        $formation->learners()->attach($learner, [
            'status' => LearnerStatus::InProgress->value,
            'enrolled_at' => now(),
        ]);
        
        $this->assertCount(1, $formation->learners);
    }

    public function test_active_learners_scope()
    {
        $formation = Formation::factory()->create();
        $learner1 = Learner::factory()->create();
        $learner2 = Learner::factory()->create();
        
        $formation->learners()->attach($learner1, [
            'status' => LearnerStatus::InProgress->value,
            'enrolled_at' => now(),
        ]);
        $formation->learners()->attach($learner2, [
            'status' => LearnerStatus::Withdrawn->value,
            'enrolled_at' => now(),
            'withdrawn_at' => now(),
        ]);
        
        $this->assertCount(1, $formation->activeLearners()->get());
    }

    public function test_withdrawn_learners_scope()
    {
        $formation = Formation::factory()->create();
        $learner1 = Learner::factory()->create();
        $learner2 = Learner::factory()->create();
        
        $formation->learners()->attach($learner1, [
            'status' => LearnerStatus::InProgress->value,
            'enrolled_at' => now(),
        ]);
        $formation->learners()->attach($learner2, [
            'status' => LearnerStatus::Withdrawn->value,
            'enrolled_at' => now(),
            'withdrawn_at' => now(),
        ]);
        
        $this->assertCount(1, $formation->withdrawnLearners()->get());
    }

    public function test_status_casting()
    {
        $formation = Formation::factory()->create([
            'status' => FormationStatus::Active,
        ]);
        
        $this->assertInstanceOf(FormationStatus::class, $formation->status);
    }

    public function test_dates_casting()
    {
        $formation = Formation::factory()->create([
            'started_at' => '2024-01-01',
            'ended_at' => '2024-06-30',
        ]);
        
        $this->assertInstanceOf(\Carbon\Carbon::class, $formation->started_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $formation->ended_at);
    }

    public function test_capacity_casting()
    {
        $formation = Formation::factory()->create([
            'capacity' => 25,
        ]);
        
        $this->assertIsInt($formation->capacity);
    }

    public function test_scope_active()
    {
        Formation::factory()->create(['status' => FormationStatus::Active]);
        Formation::factory()->create(['status' => FormationStatus::Completed]);
        
        $activeFormations = Formation::active()->get();
        
        $this->assertCount(1, $activeFormations);
        $this->assertEquals(FormationStatus::Active, $activeFormations->first()->status);
    }
}
