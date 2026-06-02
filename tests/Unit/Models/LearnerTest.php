<?php

namespace Tests\Unit\Models;

use App\Enums\Gender;
use App\Enums\LearnerStatus;
use App\Models\AgeRange;
use App\Models\EducationLevel;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Vulnerability;
use Tests\TestCase;

class LearnerTest extends TestCase
{
    public function test_learner_has_full_name_attribute()
    {
        $learner = Learner::factory()->make([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        
        $this->assertEquals('Jean Dupont', $learner->full_name);
    }

    public function test_learner_belongs_to_education_level()
    {
        $educationLevel = EducationLevel::factory()->create();
        $learner = Learner::factory()->create([
            'education_level_id' => $educationLevel->id,
        ]);
        
        $this->assertInstanceOf(EducationLevel::class, $learner->educationLevel);
        $this->assertEquals($educationLevel->id, $learner->educationLevel->id);
    }

    public function test_learner_belongs_to_age_range()
    {
        $ageRange = AgeRange::factory()->create();
        $learner = Learner::factory()->create([
            'age_range_id' => $ageRange->id,
        ]);
        
        $this->assertInstanceOf(AgeRange::class, $learner->ageRange);
    }

    public function test_learner_belongs_to_vulnerability()
    {
        $vulnerability = Vulnerability::factory()->create();
        $learner = Learner::factory()->create([
            'vulnerability_id' => $vulnerability->id,
        ]);
        
        $this->assertInstanceOf(Vulnerability::class, $learner->vulnerability);
    }

    public function test_learner_belongs_to_many_formations()
    {
        $learner = Learner::factory()->create();
        $formation = Formation::factory()->create();
        
        $learner->formations()->attach($formation, [
            'status' => LearnerStatus::InProgress->value,
            'enrolled_at' => now(),
        ]);
        
        $this->assertCount(1, $learner->formations);
        $this->assertEquals(
            LearnerStatus::InProgress->value,
            $learner->formations->first()->pivot->status
        );
    }

    public function test_learner_has_attendances()
    {
        $learner = Learner::factory()->create();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $learner->attendances());
    }

    public function test_learner_has_insertion_records()
    {
        $learner = Learner::factory()->create();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $learner->insertionRecords());
    }

    public function test_learner_search_scope()
    {
        Learner::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ]);
        Learner::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Jones',
        ]);
        
        $results = Learner::search('Alice')->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results->first()->first_name);
    }

    public function test_learner_search_by_email()
    {
        $learner = Learner::factory()->create([
            'email' => 'alice@test.com',
        ]);
        
        $results = Learner::search('alice@test')->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals($learner->id, $results->first()->id);
    }

    public function test_gender_casting()
    {
        $learner = Learner::factory()->create([
            'gender' => Gender::Male,
        ]);
        
        $this->assertInstanceOf(Gender::class, $learner->gender);
        $this->assertEquals(Gender::Male, $learner->gender);
    }

    public function test_children_count_casting()
    {
        $learner = Learner::factory()->create([
            'children_count' => 2,
        ]);
        
        $this->assertIsInt($learner->children_count);
        $this->assertEquals(2, $learner->children_count);
    }

    public function test_birth_date_casting()
    {
        $learner = Learner::factory()->create([
            'birth_date' => '1990-05-15',
        ]);
        
        $this->assertInstanceOf(\Carbon\Carbon::class, $learner->birth_date);
    }
}
