<?php

namespace Tests\Feature;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\ProjectStatus;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who may be enrolled in a formation, and what completing a formation does.
 * Both rules used to compare an enum with a string, so they never applied.
 */
class FormationEnrollmentRulesTest extends TestCase
{
    private const PROJECT_CLOSED = "Le projet de cette formation est terminé/archivé. Aucune inscription n'est possible.";

    private const FORMATION_ARCHIVED = "Cette formation est archivée. Aucune inscription n'est possible.";

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->actingAsSuperAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function formation(ProjectStatus $project = ProjectStatus::Active, FormationStatus $status = FormationStatus::Active, ?int $capacity = 30): Formation
    {
        return Formation::factory()->create([
            'project_id' => Project::factory()->create(['status' => $project])->id,
            'status' => $status,
            'capacity' => $capacity,
        ]);
    }

    private function enrolled(Formation $formation, Learner $learner): bool
    {
        return DB::table('formation_learner')->where('formation_id', $formation->id)->where('learner_id', $learner->id)->exists();
    }

    // ── Enrolling an existing learner ──────────────────────────────────────

    public function test_an_existing_learner_can_be_enrolled_in_an_open_formation(): void
    {
        $formation = $this->formation();
        $learner = Learner::factory()->create();

        $this->post(route('formations.learners.enroll', $formation), ['learner_id' => $learner->id])
            ->assertRedirect(route('formations.show', $formation))
            ->assertSessionHas('success');

        $this->assertTrue($this->enrolled($formation, $learner));
        $this->assertSame(LearnerStatus::InProgress->value, DB::table('formation_learner')->where('learner_id', $learner->id)->value('status'));
    }

    public function test_nobody_is_enrolled_in_a_formation_of_a_completed_or_archived_project(): void
    {
        foreach ([ProjectStatus::Completed, ProjectStatus::Archived] as $status) {
            $formation = $this->formation($status);
            $learner = Learner::factory()->create();

            $this->post(route('formations.learners.enroll', $formation), ['learner_id' => $learner->id])
                ->assertSessionHasErrors(['formation_id' => self::PROJECT_CLOSED]);

            $this->assertFalse($this->enrolled($formation, $learner), $status->value);
        }
    }

    public function test_nobody_is_enrolled_in_an_archived_formation(): void
    {
        $formation = $this->formation(ProjectStatus::Active, FormationStatus::Archived);
        $learner = Learner::factory()->create();

        $this->post(route('formations.learners.enroll', $formation), ['learner_id' => $learner->id])
            ->assertSessionHasErrors(['formation_id' => self::FORMATION_ARCHIVED]);

        $this->assertFalse($this->enrolled($formation, $learner));
    }

    public function test_a_learner_is_not_enrolled_twice_and_the_capacity_is_respected(): void
    {
        $formation = $this->formation(capacity: 1);
        $first = Learner::factory()->create();
        $second = Learner::factory()->create();

        $this->post(route('formations.learners.enroll', $formation), ['learner_id' => $first->id])->assertSessionHas('success');
        $this->post(route('formations.learners.enroll', $formation), ['learner_id' => $first->id])
            ->assertSessionHasErrors(['learner_id' => 'Cet apprenant est déjà inscrit à cette formation.']);
        $this->post(route('formations.learners.enroll', $formation), ['learner_id' => $second->id])
            ->assertSessionHasErrors(['formation_id' => 'La formation a atteint sa capacité maximale (1 apprenants).']);
    }

    // ── Creating a learner and enrolling them ──────────────────────────────

    public function test_a_new_learner_is_created_and_enrolled(): void
    {
        $formation = $this->formation();

        $this->post(route('formations.learners.store-new', $formation), ['first_name' => 'Awa', 'last_name' => 'Zongo'])
            ->assertRedirect(route('formations.show', $formation));

        $learner = Learner::where('last_name', 'Zongo')->firstOrFail();
        $this->assertTrue($this->enrolled($formation, $learner));
    }

    public function test_a_new_learner_is_not_created_when_the_enrolment_is_refused(): void
    {
        $closedProject = $this->formation(ProjectStatus::Completed);
        $archivedFormation = $this->formation(ProjectStatus::Active, FormationStatus::Archived);
        $full = $this->formation(capacity: 1);
        $full->learners()->attach(Learner::factory()->create()->id, ['status' => LearnerStatus::InProgress->value, 'enrolled_at' => now()]);
        $before = Learner::count();

        foreach ([$closedProject, $archivedFormation, $full] as $formation) {
            $this->post(route('formations.learners.store-new', $formation), ['first_name' => 'Refuse', 'last_name' => 'Nope'])
                ->assertSessionHasErrors('formation_id');
        }

        $this->assertSame($before, Learner::count(), 'no orphan learner must be left behind');
    }

    // ── Completing a formation ─────────────────────────────────────────────

    private function update(Formation $formation, FormationStatus $status, array $extra = [])
    {
        return $this->put(route('formations.update', $formation), [
            'name' => $formation->name,
            'started_at' => '2026-01-01',
            'status' => $status->value,
            ...$extra,
        ]);
    }

    public function test_completing_a_formation_graduates_its_active_learners_and_sets_the_end_date(): void
    {
        $formation = $this->formation();
        $active = Learner::factory()->create();
        $withdrawn = Learner::factory()->create();
        $formation->learners()->attach($active->id, ['status' => LearnerStatus::InProgress->value, 'enrolled_at' => now()]);
        $formation->learners()->attach($withdrawn->id, ['status' => LearnerStatus::Withdrawn->value, 'enrolled_at' => now()]);

        $this->update($formation, FormationStatus::Completed)->assertRedirect(route('formations.show', $formation));

        $this->assertSame(FormationStatus::Completed, $formation->fresh()->status);
        $this->assertSame('2026-11-02', $formation->fresh()->ended_at->toDateString());
        $row = DB::table('formation_learner')->where('learner_id', $active->id)->first();
        $this->assertSame(LearnerStatus::Completed->value, $row->status);
        $this->assertNotNull($row->completed_at);
        $this->assertSame(LearnerStatus::Withdrawn->value, DB::table('formation_learner')->where('learner_id', $withdrawn->id)->value('status'));
    }

    /** It used to compare an enum with a string, so every edit of a completed formation completed it again. */
    public function test_editing_an_already_completed_formation_does_not_complete_it_again(): void
    {
        $formation = $this->formation(status: FormationStatus::Completed);
        $formation->update(['ended_at' => '2026-05-05']);
        $returned = Learner::factory()->create();
        $formation->learners()->attach($returned->id, ['status' => LearnerStatus::InProgress->value, 'enrolled_at' => now()]);

        $this->update($formation, FormationStatus::Completed, ['name' => 'Renamed', 'ended_at' => '2026-05-05'])->assertRedirect();

        $this->assertSame('Renamed', $formation->fresh()->name);
        $this->assertSame('2026-05-05', $formation->fresh()->ended_at->toDateString(), 'the end date must not be overwritten');
        $this->assertSame(LearnerStatus::InProgress->value, DB::table('formation_learner')->where('learner_id', $returned->id)->value('status'), 'no second graduation');
    }
}
