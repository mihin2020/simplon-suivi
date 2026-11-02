<?php

namespace Tests\Feature;

use App\Enums\LearnerStatus;
use App\Models\Attendance;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\Trainer;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    private Formation $formation;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 14 Oct 2026: the formation started on Monday 5 Oct.
        Carbon::setTestNow('2026-10-14 10:00:00');

        $this->formation = Formation::factory()->create([
            'started_at' => '2026-10-05',
            'ended_at' => '2026-12-18',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function enroll(string $firstName, string $lastName, string $enrolledAt = '2026-10-05'): Learner
    {
        $learner = Learner::factory()->create(['first_name' => $firstName, 'last_name' => $lastName]);

        $this->formation->learners()->attach($learner->id, [
            'status' => LearnerStatus::InProgress->value,
            'enrolled_at' => $enrolledAt.' 09:00:00',
        ]);

        return $learner;
    }

    public function test_daily_sheet_is_sorted_by_family_name(): void
    {
        $this->enroll('Awa', 'Zongo');
        $this->enroll('Zacharie', 'Bambara');
        $this->enroll('Moussa', 'Ouedraogo');

        $this->actingAsSuperAdmin()
            ->get(route('attendances.index', ['formation' => $this->formation, 'date' => '2026-10-14']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendances/Index')
                ->where('learners.0.name', 'BAMBARA Zacharie')
                ->where('learners.1.name', 'OUEDRAOGO Moussa')
                ->where('learners.2.name', 'ZONGO Awa')
                ->where('learners.0.attendance', null)
                ->etc());
    }

    public function test_weekend_is_flagged_and_excluded_from_course_days(): void
    {
        $this->enroll('Awa', 'Zongo');

        $this->actingAsSuperAdmin()
            ->get(route('attendances.index', ['formation' => $this->formation, 'date' => '2026-10-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dayType.value', 'weekend')
                ->where('isCourseDay', false)
                ->has('dayStrip', 8)
                ->where('dayStrip.0.state', 'empty')
                ->etc());
    }

    public function test_day_strip_reports_partial_days(): void
    {
        $first = $this->enroll('Awa', 'Zongo');
        $this->enroll('Zacharie', 'Bambara');

        $this->actingAsSuperAdmin()->postJson(route('attendances.store-single', $this->formation), [
            'learner_id' => $first->id, 'date' => '2026-10-05', 'code' => 'P',
        ])->assertOk();

        $this->get(route('attendances.index', ['formation' => $this->formation, 'date' => '2026-10-14']))
            ->assertInertia(fn (Assert $page) => $page->where('dayStrip.0.state', 'partial')->etc());
    }

    public function test_justified_absence_keeps_its_reason_and_present_clears_it(): void
    {
        $learner = $this->enroll('Awa', 'Zongo');
        $this->actingAsSuperAdmin();

        $payload = ['learner_id' => $learner->id, 'date' => '2026-10-13'];
        $url = route('attendances.store-single', $this->formation);

        $this->postJson($url, $payload + ['code' => 'AJ', 'comment' => 'Rendez-vous médical'])->assertOk();
        $this->assertSame('Rendez-vous médical', Attendance::first()->comment);

        $this->postJson($url, $payload + ['code' => 'AN', 'comment' => 'Ignoré'])->assertOk();
        $this->assertNull(Attendance::first()->comment);

        $this->postJson($url, $payload + ['code' => 'RJ', 'comment' => 'Transport'])->assertOk();
        $this->assertSame('Transport', Attendance::first()->comment);

        $this->postJson($url, $payload + ['code' => 'P', 'comment' => 'Transport'])->assertOk();
        $this->assertNull(Attendance::first()->comment);
        $this->assertSame(1, Attendance::count());
    }

    public function test_future_day_and_foreign_learner_are_rejected(): void
    {
        $enrolled = $this->enroll('Awa', 'Zongo');
        $stranger = Learner::factory()->create();
        $this->actingAsSuperAdmin();
        $url = route('attendances.store-single', $this->formation);

        $this->postJson($url, ['learner_id' => $enrolled->id, 'date' => '2026-10-20', 'code' => 'P'])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->postJson($url, ['learner_id' => $stranger->id, 'date' => '2026-10-13', 'code' => 'P'])
            ->assertUnprocessable()->assertJsonValidationErrors('learner_id');
    }

    public function test_bulk_store_marks_the_given_learners(): void
    {
        $a = $this->enroll('Awa', 'Zongo');
        $b = $this->enroll('Zacharie', 'Bambara');
        $this->actingAsSuperAdmin();

        $this->post(route('attendances.store', $this->formation), [
            'date' => '2026-10-13',
            'records' => [$a->id => ['code' => 'P'], $b->id => ['code' => 'AJ', 'comment' => 'Deuil']],
        ])->assertRedirect();

        $this->assertSame(2, Attendance::count());
        $this->assertSame('Deuil', Attendance::where('learner_id', $b->id)->first()->comment);
    }

    public function test_late_joiner_is_not_counted_absent_for_earlier_days(): void
    {
        $early = $this->enroll('Awa', 'Zongo');
        $late = $this->enroll('Zacharie', 'Bambara', '2026-10-13');
        $this->actingAsSuperAdmin();
        $url = route('attendances.store-single', $this->formation);

        foreach (['2026-10-13', '2026-10-14'] as $day) {
            foreach ([$early, $late] as $learner) {
                $this->postJson($url, ['learner_id' => $learner->id, 'date' => $day, 'code' => 'P'])->assertOk();
            }
        }

        $this->get(route('attendances.recap', ['formation' => $this->formation, 'view' => 'all']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recap.rows.0.name', 'BAMBARA Zacharie')
                ->where('recap.rows.0.expected', 2)
                ->where('recap.rows.0.recorded', 2)
                ->where('recap.rows.0.rate', 100)
                ->where('recap.rows.0.days.2026-10-12.enrolled', false)
                ->where('recap.rows.1.expected', 8)
                ->where('recap.rows.1.recorded', 2)
                ->where('recap.rows.1.days.2026-10-12.enrolled', true)
                ->etc());
    }

    public function test_recap_week_view_is_limited_to_the_week_and_navigable(): void
    {
        $this->enroll('Awa', 'Zongo');
        $this->actingAsSuperAdmin();

        $this->get(route('attendances.recap', ['formation' => $this->formation, 'view' => 'week', 'anchor' => '2026-10-14']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recap.period.view', 'week')
                ->where('recap.period.prev', '2026-10-05')
                ->where('recap.period.next', null)
                ->where('recap.days', ['2026-10-12', '2026-10-13', '2026-10-14'])
                ->where('recap.weeks.0.number', 2)
                ->etc());
    }

    public function test_pdfs_are_generated(): void
    {
        $learner = $this->enroll('Awa', 'Zongo');
        $this->actingAsSuperAdmin();

        $this->postJson(route('attendances.store-single', $this->formation), [
            'learner_id' => $learner->id, 'date' => '2026-10-13', 'code' => 'AJ', 'comment' => 'Examen',
        ])->assertOk();

        $this->get(route('attendances.pdf', ['formation' => $this->formation, 'date' => '2026-10-13']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('attendances.pdf-recap', ['formation' => $this->formation, 'view' => 'all']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('attendances.pdf-recap', ['formation' => $this->formation, 'view' => 'week', 'anchor' => '2026-10-13']))
            ->assertOk();
    }

    public function test_trainer_of_the_formation_can_record_but_another_trainer_cannot(): void
    {
        $learner = $this->enroll('Awa', 'Zongo');

        $assigned = $this->createTrainer();
        $trainer = Trainer::create(['user_id' => $assigned->id]);
        $this->formation->trainers()->attach($trainer->id, ['is_lead' => true, 'assigned_at' => now()]);

        $payload = ['learner_id' => $learner->id, 'date' => '2026-10-13', 'code' => 'P'];

        $this->actingAs($this->createTrainer())
            ->postJson(route('attendances.store-single', $this->formation), $payload)
            ->assertForbidden();

        $this->actingAs($assigned)
            ->postJson(route('attendances.store-single', $this->formation), $payload)
            ->assertOk();
    }

    public function test_day_strip_ignores_withdrawn_learners_records(): void
    {
        $a = $this->enroll('Awa', 'Zongo');
        $this->enroll('Zacharie', 'Bambara');
        $gone = $this->enroll('Moussa', 'Ouedraogo');
        $this->actingAsSuperAdmin();
        $url = route('attendances.store-single', $this->formation);

        foreach ([$a, $gone] as $learner) {
            $this->postJson($url, ['learner_id' => $learner->id, 'date' => '2026-10-13', 'code' => 'P'])->assertOk();
        }
        $this->formation->learners()->updateExistingPivot($gone->id, ['status' => LearnerStatus::Withdrawn->value, 'withdrawn_at' => '2026-10-14']);

        $this->get(route('attendances.index', ['formation' => $this->formation, 'date' => '2026-10-13']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dayStrip.6.date', '2026-10-13')
                ->where('dayStrip.6.state', 'partial')
                ->etc());
    }

    public function test_recap_keeps_history_of_withdrawn_learner(): void
    {
        $this->enroll('Awa', 'Zongo');
        $gone = $this->enroll('Moussa', 'Ouedraogo');
        $this->actingAsSuperAdmin();
        $this->postJson(route('attendances.store-single', $this->formation), ['learner_id' => $gone->id, 'date' => '2026-10-06', 'code' => 'P'])->assertOk();
        $this->formation->learners()->updateExistingPivot($gone->id, ['status' => LearnerStatus::Withdrawn->value, 'withdrawn_at' => '2026-10-07']);

        $this->get(route('attendances.recap', ['formation' => $this->formation, 'view' => 'all']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recap.rows.0.name', 'OUEDRAOGO Moussa')
                ->where('recap.rows.0.recorded', 1)
                ->where('recap.rows.0.expected', 3)
                ->etc());
    }

    public function test_date_outside_formation_period_is_rejected(): void
    {
        $learner = $this->enroll('Awa', 'Zongo');
        $this->actingAsSuperAdmin();

        $this->postJson(route('attendances.store-single', $this->formation), ['learner_id' => $learner->id, 'date' => '2026-09-28', 'code' => 'P'])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->post(route('attendances.store', $this->formation), ['date' => '2026-09-28', 'records' => [$learner->id => ['code' => 'P']]])
            ->assertSessionHasErrors('date');
    }

    public function test_week_view_includes_exceptional_saturday(): void
    {
        $learner = $this->enroll('Awa', 'Zongo');
        $this->actingAsSuperAdmin();
        $this->postJson(route('attendances.store-single', $this->formation), ['learner_id' => $learner->id, 'date' => '2026-10-10', 'code' => 'P'])->assertOk();

        $this->get(route('attendances.recap', ['formation' => $this->formation, 'view' => 'week', 'anchor' => '2026-10-07']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recap.rows.0.days.2026-10-10.code', 'P')
                ->etc());
    }
}
