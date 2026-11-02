<?php

namespace Tests\Feature;

use App\Enums\AttendanceCode;
use App\Enums\FormationStatus;
use App\Enums\InsertionStatus;
use App\Enums\LearnerStatus;
use App\Models\Attendance;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Project;
use App\Models\Trainer;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pins the figures of the home page, for an administrator and for a trainer. */
class DashboardReportTest extends TestCase
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

    private function props(): array
    {
        return $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];
    }

    private function learner(string $name, ?string $gender, array $enrolments = []): Learner
    {
        $learner = Learner::factory()->create(['first_name' => $name, 'last_name' => $name, 'gender' => $gender]);

        foreach ($enrolments as [$formation, $status, $at]) {
            $formation->learners()->attach($learner->id, ['status' => $status->value, 'enrolled_at' => $at]);
        }

        return $learner;
    }

    // ── Administrator ──────────────────────────────────────────────────────

    /** @return array<string, Formation> */
    private function seedAdminData(): array
    {
        $running = Project::factory()->create(['name' => 'Running']);
        $closed = Project::factory()->create(['name' => 'Closed']);

        $f1 = Formation::factory()->create(['project_id' => $running->id, 'name' => 'F1', 'status' => FormationStatus::Active, 'started_at' => '2026-10-01', 'ended_at' => '2026-12-15']);
        $f2 = Formation::factory()->create(['project_id' => $running->id, 'name' => 'F2', 'status' => FormationStatus::Active, 'started_at' => '2026-09-01', 'ended_at' => null]);
        $f3 = Formation::factory()->create(['project_id' => $closed->id, 'name' => 'F3', 'status' => FormationStatus::Completed, 'started_at' => '2026-01-01']);

        $ann = $this->learner('Ann', 'female', [[$f1, LearnerStatus::InProgress, '2026-10-05 08:00:00']]);
        $bob = $this->learner('Bob', 'male', [[$f1, LearnerStatus::InProgress, '2026-10-06 08:00:00'], [$f2, LearnerStatus::InProgress, '2026-10-07 08:00:00']]);
        $cleo = $this->learner('Cleo', 'female', [[$f3, LearnerStatus::Completed, '2026-02-01 08:00:00']]);
        $dan = $this->learner('Dan', 'male', [[$f1, LearnerStatus::Withdrawn, '2026-10-08 08:00:00']]);
        $this->learner('Eva', null);
        $gone = $this->learner('Gone', 'male', [[$f1, LearnerStatus::InProgress, '2026-10-09 08:00:00']]);
        $gone->delete();

        foreach ([
            [$ann, 'searching', '2026-01-01'], [$ann, 'employed', '2026-03-01'],
            [$bob, 'internship', '2026-02-01'],
            [$cleo, 'unemployed', '2026-02-01'],
            [$dan, 'employed', '2026-02-01'],
        ] as [$learner, $status, $at]) {
            InsertionRecord::create(['learner_id' => $learner->id, 'status' => $status, 'status_changed_at' => $at.' 10:00:00']);
        }

        Trainer::create(['user_id' => User::factory()->create()->id]);
        Trainer::create(['user_id' => User::factory()->create()->id]);

        return compact('f1', 'f2', 'f3');
    }

    public function test_the_administrator_sees_the_platform_totals(): void
    {
        $this->seedAdminData();
        $this->actingAsSuperAdmin();

        $props = $this->props();

        $this->assertSame('admin', $props['role']);
        // 5 learners alive (Eva has no formation, Gone is deleted); Ann, Bob and Gone are in progress somewhere,
        // but Gone is deleted: 2 active. Only the "Running" project has an active formation.
        $this->assertSame(['total' => 2, 'active' => 1], $props['stats']['projects']);
        $this->assertSame(['total' => 3, 'active' => 2], $props['stats']['formations']);
        $this->assertSame(['total' => 5, 'active' => 2], $props['stats']['learners']);
        $this->assertSame(['total' => 2], $props['stats']['trainers']);
        // Latest status per learner: Ann employed, Bob internship, Cleo unemployed, Dan employed -> 3 of 4 inserted.
        $this->assertSame(75, $props['stats']['insertion_rate']);
        $this->assertSame(4, $props['insertionTracked']);
        $this->assertSame(['male' => 2, 'female' => 2], $props['gender']);
    }

    public function test_the_administrator_sees_the_breakdowns(): void
    {
        $this->seedAdminData();
        $this->actingAsSuperAdmin();

        $props = $this->props();

        $this->assertSame(
            [['key' => 'in_progress', 'count' => 2], ['key' => 'withdrawn', 'count' => 1], ['key' => 'completed', 'count' => 1], ['key' => 'moved', 'count' => 0]],
            array_map(fn (array $row) => ['key' => $row['key'], 'count' => $row['count']], $props['learnerStatus']),
        );
        $this->assertSame(
            [['key' => 'searching', 'count' => 0], ['key' => 'internship', 'count' => 1], ['key' => 'employed', 'count' => 2], ['key' => 'unemployed', 'count' => 1]],
            array_map(fn (array $row) => ['key' => $row['key'], 'count' => $row['count']], $props['insertion']),
        );
        $this->assertSame(InsertionStatus::Employed->label(), $props['insertion'][2]['label']);
        $this->assertSame(LearnerStatus::Withdrawn->label(), $props['learnerStatus'][1]['label']);
    }

    public function test_the_administrator_sees_active_formations_and_recent_enrolments(): void
    {
        $this->seedAdminData();
        $this->actingAsSuperAdmin();

        $props = $this->props();

        $formations = $props['activeFormations']['data'];
        $this->assertSame(['F1', 'F2'], array_column($formations, 'name'), 'most recently started first');
        $this->assertSame('Running', $formations[0]['project_name']);
        $this->assertSame(2, $formations[0]['active_learners_count'], 'Gone is deleted but still counted by the pivot query');
        $this->assertSame(1, $formations[1]['active_learners_count']);
        $this->assertSame('01/10/2026', $formations[0]['started_at']);
        $this->assertSame('15/12/2026', $formations[0]['ended_at']);
        $this->assertNull($formations[1]['ended_at']);

        $recent = $props['recentEnrollments'];
        $this->assertSame(['Dan', 'Bob', 'Bob', 'Ann', 'Cleo'], array_column($recent, 'first_name'), 'newest enrolment first, deleted learners left out');
        $this->assertSame(['F1', 'F2', 'F1', 'F1', 'F3'], array_column($recent, 'formation_name'));
    }

    public function test_at_most_five_active_formations_and_recent_enrolments_are_listed(): void
    {
        $project = Project::factory()->create();
        $formations = Formation::factory()->count(7)->create(['project_id' => $project->id, 'status' => FormationStatus::Active]);
        foreach ($formations as $i => $formation) {
            $this->learner("L{$i}", 'male', [[$formation, LearnerStatus::InProgress, now()->subDays($i)]]);
        }
        $this->actingAsSuperAdmin();

        $props = $this->props();

        $this->assertCount(5, $props['activeFormations']['data']);
        $this->assertSame(7, $props['activeFormations']['total']);
        $this->assertCount(5, $props['recentEnrollments']);
    }

    public function test_an_empty_platform_gives_zeros(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->props();

        $this->assertSame(0, $props['stats']['insertion_rate']);
        $this->assertSame(0, $props['insertionTracked']);
        $this->assertSame(['male' => 0, 'female' => 0], $props['gender']);
        $this->assertSame([], $props['activeFormations']['data']);
        $this->assertSame([], $props['recentEnrollments']);
    }

    // ── Trainer ────────────────────────────────────────────────────────────

    public function test_a_trainer_without_a_profile_sees_an_empty_dashboard(): void
    {
        $this->actingAsTrainer();

        $this->assertSame([
            'role' => 'trainer', 'trainer' => null, 'myFormations' => [],
            'trainerStats' => ['formations' => 0, 'learners' => 0, 'attendances_today' => 0],
        ], array_intersect_key($this->props(), array_flip(['role', 'trainer', 'myFormations', 'trainerStats'])));
    }

    public function test_a_trainer_sees_their_formations_and_the_attendance_to_enter(): void
    {
        $user = $this->createTrainer();
        $trainer = Trainer::create(['user_id' => $user->id]);
        $project = Project::factory()->create(['name' => 'Proj']);

        $needsSheet = Formation::factory()->create(['project_id' => $project->id, 'name' => 'NeedsSheet', 'status' => FormationStatus::Active, 'started_at' => '2026-10-10', 'ended_at' => '2026-11-20']);
        $done = Formation::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'status' => FormationStatus::Active, 'started_at' => '2026-10-01', 'ended_at' => '2027-06-01']);
        $old = Formation::factory()->create(['project_id' => $project->id, 'name' => 'Old', 'status' => FormationStatus::Completed, 'started_at' => '2026-01-01', 'ended_at' => '2026-03-01']);
        $other = Formation::factory()->create(['project_id' => $project->id, 'name' => 'NotMine', 'status' => FormationStatus::Active]);

        $trainer->formations()->attach($needsSheet->id, ['is_lead' => true, 'assigned_at' => now()]);
        $trainer->formations()->attach($done->id, ['is_lead' => false, 'assigned_at' => now()]);
        $trainer->formations()->attach($old->id, ['is_lead' => false, 'assigned_at' => now()]);

        $ann = $this->learner('Ann', 'female', [[$needsSheet, LearnerStatus::InProgress, now()], [$done, LearnerStatus::InProgress, now()]]);
        $bob = $this->learner('Bob', 'male', [[$needsSheet, LearnerStatus::InProgress, now()], [$done, LearnerStatus::InProgress, now()]]);
        $this->learner('Stranger', 'male', [[$other, LearnerStatus::InProgress, now()]]);

        // The "Done" formation already has today's sheet; "NeedsSheet" does not.
        Attendance::create(['formation_id' => $done->id, 'learner_id' => $ann->id, 'date' => today(), 'code' => AttendanceCode::Present, 'recorded_by' => $user->id]);

        $this->actingAs($user);
        $props = $this->props();

        $this->assertSame('trainer', $props['role']);
        $this->assertSame(['formations' => 3, 'learners' => 2, 'attendances_today' => 1], $props['trainerStats']);
        $this->assertSame(['NeedsSheet', 'Done', 'Old'], array_column($props['myFormations'], 'name'), 'most recently started first');

        [$a, $b, $c] = $props['myFormations'];
        $this->assertTrue($a['needs_attendance_today']);
        $this->assertTrue($a['ending_soon'], 'ends in 18 days');
        $this->assertTrue($a['is_lead']);
        $this->assertSame(2, $a['active_learners_count']);
        $this->assertSame('10/10/2026', $a['started_at']);

        $this->assertFalse($b['needs_attendance_today']);
        $this->assertSame(1, $b['attendances_today_count']);
        $this->assertFalse($b['ending_soon']);
        $this->assertFalse($b['is_lead']);

        $this->assertSame('completed', $c['status']);
        $this->assertFalse($c['needs_attendance_today']);
        $this->assertFalse($c['ending_soon'], 'only an active formation can be ending soon');
    }

    public function test_a_trainer_sees_repeated_absences_of_the_last_seven_days(): void
    {
        $user = $this->createTrainer();
        $trainer = Trainer::create(['user_id' => $user->id]);
        $formation = Formation::factory()->create(['project_id' => Project::factory()->create()->id, 'name' => 'F', 'status' => FormationStatus::Active]);
        $trainer->formations()->attach($formation->id, ['is_lead' => true, 'assigned_at' => now()]);

        $often = $this->learner('Often', 'male', [[$formation, LearnerStatus::InProgress, now()]]);
        $once = $this->learner('Once', 'female', [[$formation, LearnerStatus::InProgress, now()]]);
        $tooOld = $this->learner('TooOld', 'male', [[$formation, LearnerStatus::InProgress, now()]]);

        $record = fn (Learner $learner, string $date, AttendanceCode $code) => Attendance::create(['formation_id' => $formation->id, 'learner_id' => $learner->id, 'date' => $date, 'code' => $code, 'recorded_by' => $user->id]);
        $record($often, '2026-10-30', AttendanceCode::AbsentJustified);
        $record($often, '2026-11-02', AttendanceCode::AbsentNotJustified);
        $record($often, '2026-11-01', AttendanceCode::Present);
        $record($once, '2026-11-02', AttendanceCode::AbsentNotJustified);
        $record($tooOld, '2026-10-20', AttendanceCode::AbsentNotJustified);
        $record($tooOld, '2026-10-21', AttendanceCode::AbsentNotJustified);

        $this->actingAs($user);
        $absences = json_decode(json_encode($this->props()['recentAbsences']), true); // raw database rows

        $this->assertCount(1, $absences, 'two absences within 7 days are needed');
        $this->assertSame('Often', $absences[0]['first_name']);
        $this->assertSame('F', $absences[0]['formation_name']);
        $this->assertEquals(2, $absences[0]['absences_count']);
    }

    public function test_the_trainer_speciality_is_made_of_their_profiles(): void
    {
        $user = $this->createTrainer();
        $trainer = Trainer::create(['user_id' => $user->id]);
        $trainer->profiles()->attach([
            TrainerProfile::create(['name' => 'Développeur web', 'order' => 1])->id,
            TrainerProfile::create(['name' => 'Data analyste', 'order' => 2])->id,
        ]);

        $this->actingAs($user);
        $props = $this->props();

        $this->assertSame($user->full_name, $props['trainer']['full_name']);
        $this->assertEqualsCanonicalizing(['Développeur web', 'Data analyste'], explode(', ', $props['trainer']['speciality']));
    }

    public function test_a_trainer_without_profile_has_no_speciality(): void
    {
        $user = $this->createTrainer();
        Trainer::create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->assertNull($this->props()['trainer']['speciality']);
    }
}
