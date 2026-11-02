<?php

namespace Tests\Feature\Campus;

use App\Enums\CampusFormationMode;
use App\Enums\CohortStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pins the figures of the campus finance page on data computed by hand. Today is 2026-11-02.
 *
 *  Formation A (cost 100 000)   C1 2026-01: Ann, Bob active, Cleo abandoned (3 enrolled)
 *                                 Ann   paid 100 000 on 2026-11-01
 *                                 Bob   paid  40 000 on 2026-10-01, 60 000 pending, due long ago
 *                                 Cleo  paid  10 000 on 2026-11-02
 *                               C2 2026-03: Dan active, 100 000 pending due today
 *  Formation B (cost  50 000)   C3 2026-06: Eva active, paid 50 000 on 2026-10-20, 20 000 late (due in the future)
 *  Formation A                  C4 2025-05: Fay active, nothing paid (another year)
 */
class CampusFinanceOverviewTest extends TestCase
{
    /** @var array<string, Cohort> */
    private array $cohorts = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 10:00:00');

        $a = $this->formation('Formation A', 100000);
        $b = $this->formation('Formation B', 50000);

        $this->cohorts['C1'] = $this->cohort($a, 'C1', '2026-01-10');
        $this->cohorts['C2'] = $this->cohort($a, 'C2', '2026-03-01');
        $this->cohorts['C3'] = $this->cohort($b, 'C3', '2026-06-01');
        $this->cohorts['C4'] = $this->cohort($a, 'C4', '2025-05-01');

        [$ann, $bob, $cleo, $dan, $eva, $fay] = collect(['Ann', 'Bob', 'Cleo', 'Dan', 'Eva', 'Fay'])
            ->map(fn (string $name) => Learner::factory()->create(['first_name' => $name, 'last_name' => $name]))
            ->all();

        $this->cohorts['C1']->learners()->attach([$ann->id => ['enrolled_at' => now(), 'status' => 'actif'], $bob->id => ['enrolled_at' => now(), 'status' => 'actif'], $cleo->id => ['enrolled_at' => now(), 'status' => 'retrait']]);
        $this->cohorts['C2']->learners()->attach($dan->id, ['enrolled_at' => now(), 'status' => 'actif']);
        $this->cohorts['C3']->learners()->attach($eva->id, ['enrolled_at' => now(), 'status' => 'actif']);
        $this->cohorts['C4']->learners()->attach($fay->id, ['enrolled_at' => now(), 'status' => 'actif']);

        $this->payment('C1', $ann, PaymentStatus::Paye, 100000, '2026-10-01', '2026-11-01');
        $this->payment('C1', $bob, PaymentStatus::Paye, 40000, '2026-09-01', '2026-10-01');
        $this->payment('C1', $bob, PaymentStatus::EnAttente, 60000, '2020-01-01');
        $this->payment('C1', $cleo, PaymentStatus::Paye, 10000, '2026-10-01', '2026-11-02');
        $this->payment('C2', $dan, PaymentStatus::EnAttente, 100000, '2026-11-02');
        $this->payment('C3', $eva, PaymentStatus::Paye, 50000, '2026-10-01', '2026-10-20');
        $this->payment('C3', $eva, PaymentStatus::EnRetard, 20000, '2099-01-01');

        $this->actingAsSuperAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function formation(string $name, int $cost): CampusFormation
    {
        return CampusFormation::create([
            'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'duration_months' => 6,
            'mode' => CampusFormationMode::Presentiel, 'total_cost' => $cost, 'is_active' => true,
        ]);
    }

    private function cohort(CampusFormation $formation, string $name, string $startedAt): Cohort
    {
        return Cohort::create([
            'campus_formation_id' => $formation->id, 'name' => $name, 'started_at' => $startedAt,
            'ended_at' => Carbon::parse($startedAt)->addMonths(6)->toDateString(), 'status' => CohortStatus::EnCours,
        ]);
    }

    private function payment(string $cohort, Learner $learner, PaymentStatus $status, int $amount, string $due, ?string $paidAt = null): void
    {
        Payment::create([
            'cohort_id' => $this->cohorts[$cohort]->id, 'learner_id' => $learner->id, 'amount' => $amount,
            'installment_number' => 1, 'due_date' => $due, 'paid_at' => $paidAt, 'status' => $status,
            'payment_method' => $status === PaymentStatus::Paye ? PaymentMethod::Especes : null,
        ]);
    }

    private function props(array $query = []): array
    {
        return $this->get(route('campus.finance.index', $query))->assertOk()->viewData('page')['props'];
    }

    public function test_the_global_figures_of_the_year(): void
    {
        $props = $this->props();

        $this->assertSame(2026, $props['selectedYear']);
        $this->assertSame([2026, 2025], $props['years']);
        $this->assertEquals([
            'total_expected' => 350000,   // 100k x 2 active (C1) + 100k x 1 (C2) + 50k x 1 (C3)
            'total_collected' => 200000,  // every paid payment of the year, the abandoned learner's included
            'total_remaining' => 150000,
            'overdue_count' => 1,         // pending AND due before today: Bob only (Dan is due today, Eva is "late")
            'paid_this_month' => 110000,  // paid in November 2026: Ann 100 000 + Cleo 10 000
        ], $props['globalStats']);
    }

    public function test_the_cohorts_are_grouped_by_formation(): void
    {
        $byFormation = $this->props()['byFormation'];

        $this->assertSame(['Formation A', 'Formation B'], array_column($byFormation, 'name'));
        $this->assertSame(['C1', 'C2'], array_column($byFormation[0]['cohorts'], 'name'), 'by start date');
        $this->assertEquals(['total_expected' => 300000, 'total_collected' => 150000, 'total_remaining' => 150000, 'overdue_count' => 2, 'collect_rate' => 50], $byFormation[0]['totals']);
        $this->assertEquals(['total_expected' => 50000, 'total_collected' => 50000, 'total_remaining' => 0, 'overdue_count' => 1, 'collect_rate' => 100], $byFormation[1]['totals']);
    }

    public function test_the_figures_of_one_cohort(): void
    {
        $c1 = $this->props()['byFormation'][0]['cohorts'][0];

        $this->assertSame($this->cohorts['C1']->id, $c1['id']);
        $this->assertSame('2026-01-10', $c1['started_at']);
        $this->assertSame('2026-07-10', $c1['ended_at']);
        $this->assertSame('en_cours', $c1['status']);
        $this->assertSame(3, $c1['learners_count']);
        $this->assertSame(2, $c1['active_count']);
        $this->assertSame(100000, $c1['unit_cost']);
        $this->assertSame(200000, $c1['total_expected']);
        $this->assertSame(150000, $c1['total_collected']);
        $this->assertSame(50000, $c1['total_remaining']);
        $this->assertSame(1, $c1['overdue_count']);
        $this->assertSame(75, $c1['collect_rate']);
        $this->assertSame('Formation A', $c1['formation']['name']);
    }

    public function test_a_payment_due_today_or_late_counts_as_overdue_for_the_cohort_but_not_for_the_global_figure(): void
    {
        $byFormation = $this->props()['byFormation'];

        // C2: Dan's installment is due today; C3: Eva's is flagged "en retard". Both are overdue in the cohort
        // figure (PHP check "is past" / late status), unlike the global counter, which only takes pending
        // installments due strictly before today.
        $this->assertSame(1, $byFormation[0]['cohorts'][1]['overdue_count']);
        $this->assertSame(1, $byFormation[1]['cohorts'][0]['overdue_count']);
    }

    public function test_a_cohort_collected_more_than_expected_is_capped_at_100_percent(): void
    {
        Payment::create(['cohort_id' => $this->cohorts['C3']->id, 'learner_id' => Learner::factory()->create()->id, 'amount' => 90000, 'installment_number' => 3, 'due_date' => '2026-09-01', 'paid_at' => '2026-09-01', 'status' => PaymentStatus::Paye, 'payment_method' => PaymentMethod::Especes]);

        $c3 = $this->props()['byFormation'][1]['cohorts'][0];

        $this->assertSame(100, $c3['collect_rate']);
        $this->assertSame(0, $c3['total_remaining']);
    }

    public function test_the_latest_overdue_installments_are_listed(): void
    {
        $recent = $this->props()['recentOverdue'];

        $this->assertCount(1, $recent);
        $this->assertSame(60000, $recent[0]['amount']);
        $this->assertSame('2020-01-01', $recent[0]['due_date']);
        $this->assertSame('Bob', $recent[0]['learner']['first_name']);
        $this->assertSame('C1', $recent[0]['cohort']['name']);
        $this->assertSame('Formation A', $recent[0]['cohort']['campus_formation']['name']);
    }

    public function test_another_year_can_be_selected(): void
    {
        $props = $this->props(['year' => 2025]);

        $this->assertSame(2025, $props['selectedYear']);
        $this->assertSame(['C4'], array_column($props['byFormation'][0]['cohorts'], 'name'));
        $this->assertEquals(100000, $props['globalStats']['total_expected']);
        $this->assertEquals(0, $props['globalStats']['total_collected']);
        $this->assertSame([], $props['recentOverdue']);
    }

    public function test_a_year_without_cohort_is_empty(): void
    {
        $props = $this->props(['year' => 1999]);

        $this->assertSame([], $props['byFormation']);
        $this->assertEquals(['total_expected' => 0, 'total_collected' => 0, 'total_remaining' => 0, 'overdue_count' => 0, 'paid_this_month' => 0], $props['globalStats']);
    }

    public function test_without_any_cohort_the_current_year_is_offered(): void
    {
        Cohort::query()->forceDelete();

        $this->assertSame([2026], $this->props()['years']);
    }

    public function test_the_page_needs_a_finance_permission(): void
    {
        $this->actingAsTrainer();

        $this->get(route('campus.finance.index'))->assertForbidden();
    }
}
