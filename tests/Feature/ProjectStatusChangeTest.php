<?php

namespace Tests\Feature;

use App\Enums\FormationStatus;
use App\Enums\LearnerStatus;
use App\Enums\ProjectStatus;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Partner;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Pins what happens to a project, its formations and its learners when the project is closed or archived. */
class ProjectStatusChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{project: Project, running: Formation, finished: Formation, cancelled: Formation, inProgress: Learner, withdrawn: Learner, alsoInProgress: Learner} */
    private function seedProject(): array
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Active]);

        $running = Formation::factory()->create(['project_id' => $project->id, 'status' => FormationStatus::Active, 'ended_at' => '2026-12-31']);
        $finished = Formation::factory()->create(['project_id' => $project->id, 'status' => FormationStatus::Completed, 'ended_at' => '2026-06-30']);
        $cancelled = Formation::factory()->create(['project_id' => $project->id, 'status' => FormationStatus::Cancelled, 'ended_at' => '2026-12-31']);

        $inProgress = $this->enroll($running, LearnerStatus::InProgress);
        $withdrawn = $this->enroll($running, LearnerStatus::Withdrawn);
        $alsoInProgress = $this->enroll($finished, LearnerStatus::InProgress);

        return compact('project', 'running', 'finished', 'cancelled', 'inProgress', 'withdrawn', 'alsoInProgress');
    }

    private function enroll(Formation $formation, LearnerStatus $status): Learner
    {
        $learner = Learner::factory()->create();
        $formation->learners()->attach($learner->id, ['status' => $status->value, 'enrolled_at' => now()]);

        return $learner;
    }

    private function update(Project $project, string $status, array $extra = [])
    {
        return $this->actingAsSuperAdmin()->put(route('projects.update', $project), [
            'name' => $project->name,
            'started_at' => '2026-09-01',
            'status' => $status,
            ...$extra,
        ]);
    }

    private function pivot(Formation $formation, Learner $learner): object
    {
        return DB::table('formation_learner')->where('formation_id', $formation->id)->where('learner_id', $learner->id)->first();
    }

    public function test_completing_a_project_finishes_and_archives_its_formations_and_graduates_active_learners(): void
    {
        $data = $this->seedProject();

        $this->update($data['project'], 'completed')
            ->assertRedirect(route('projects.show', $data['project']))
            ->assertSessionHas('success', 'Projet terminé avec succès. Bilan : 2/3 apprenants ayant terminé la formation.');

        $project = $data['project']->fresh();
        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertSame('2026-11-02', $project->ended_at->toDateString());

        // Every formation ends up archived; only those that were not finished get an end date.
        foreach (['running', 'finished', 'cancelled'] as $key) {
            $this->assertSame(FormationStatus::Archived, $data[$key]->fresh()->status, $key);
        }
        $this->assertSame('2026-11-02', $data['running']->fresh()->ended_at->toDateString());
        $this->assertSame('2026-11-02', $data['cancelled']->fresh()->ended_at->toDateString());
        $this->assertSame('2026-06-30', $data['finished']->fresh()->ended_at->toDateString());

        // Active learners graduate, the others are left as they were.
        foreach ([[$data['running'], $data['inProgress']], [$data['finished'], $data['alsoInProgress']]] as [$formation, $learner]) {
            $row = $this->pivot($formation, $learner);
            $this->assertSame(LearnerStatus::Completed->value, $row->status);
            $this->assertNotNull($row->completed_at);
        }
        $this->assertSame(LearnerStatus::Withdrawn->value, $this->pivot($data['running'], $data['withdrawn'])->status);
    }

    public function test_archiving_a_project_has_the_same_effect_with_its_own_message(): void
    {
        $data = $this->seedProject();

        $this->update($data['project'], 'archived')
            ->assertSessionHas('success', 'Projet archivé avec succès. Bilan : 2/3 apprenants ayant terminé la formation.');

        $this->assertSame(ProjectStatus::Archived, $data['project']->fresh()->status);
        $this->assertSame('2026-11-02', $data['project']->fresh()->ended_at->toDateString());
        $this->assertSame(FormationStatus::Archived, $data['running']->fresh()->status);
        $this->assertSame(LearnerStatus::Completed->value, $this->pivot($data['running'], $data['inProgress'])->status);
    }

    public function test_an_ordinary_update_leaves_the_formations_alone(): void
    {
        $data = $this->seedProject();

        $this->update($data['project'], 'active', ['name' => 'Renamed'])
            ->assertSessionHas('success', 'Projet mis à jour avec succès.');

        $this->assertSame('Renamed', $data['project']->fresh()->name);
        $this->assertSame(FormationStatus::Active, $data['running']->fresh()->status);
        $this->assertSame(LearnerStatus::InProgress->value, $this->pivot($data['running'], $data['inProgress'])->status);
    }

    public function test_the_partners_are_synchronised_on_update(): void
    {
        $data = $this->seedProject();
        $partners = collect(['Alpha', 'Beta'])->map(fn (string $name) => Partner::create(['name' => $name, 'category' => 'bailleur']));

        $this->update($data['project'], 'active', ['partner_ids' => $partners->pluck('id')->all()])->assertRedirect();
        $this->assertCount(2, $data['project']->fresh()->partners);

        $this->update($data['project'], 'active')->assertRedirect();
        $this->assertCount(0, $data['project']->fresh()->partners);
    }

    public function test_an_update_is_validated_and_authorised(): void
    {
        $data = $this->seedProject();

        $this->update($data['project'], 'not-a-status')->assertSessionHasErrors('status');

        $this->actingAsTrainer()
            ->put(route('projects.update', $data['project']), ['name' => 'x', 'started_at' => '2026-09-01', 'status' => 'completed'])
            ->assertForbidden();
        $this->assertSame(ProjectStatus::Active, $data['project']->fresh()->status);
    }

    /** It used to compare an enum with a string, so every edit of a closed project closed it again. */
    public function test_editing_an_already_closed_project_does_not_close_it_again(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Completed, 'started_at' => '2026-01-01', 'ended_at' => '2026-05-05']);
        $formation = Formation::factory()->create(['project_id' => $project->id, 'status' => FormationStatus::Active]);
        $learner = $this->enroll($formation, LearnerStatus::InProgress);

        $this->update($project, 'completed', ['name' => 'New name', 'started_at' => '2026-01-01', 'ended_at' => '2026-05-05'])
            ->assertSessionHas('success', 'Projet mis à jour avec succès.');

        $this->assertSame('New name', $project->fresh()->name);
        $this->assertSame('2026-05-05', $project->fresh()->ended_at->toDateString(), 'the end date must not be overwritten');
        $this->assertSame(FormationStatus::Active, $formation->fresh()->status, 'no second cascade');
        $this->assertSame(LearnerStatus::InProgress->value, $this->pivot($formation, $learner)->status);
    }

    public function test_going_from_completed_to_archived_is_a_real_change(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::Completed, 'started_at' => '2026-01-01', 'ended_at' => '2026-05-05']);
        $formation = Formation::factory()->create(['project_id' => $project->id, 'status' => FormationStatus::Completed]);

        $this->update($project, 'archived', ['started_at' => '2026-01-01', 'ended_at' => '2026-05-05'])
            ->assertSessionHas('success', 'Projet archivé avec succès. Bilan : 0/0 apprenants ayant terminé la formation.');

        $this->assertSame(FormationStatus::Archived, $formation->fresh()->status);
    }
}
