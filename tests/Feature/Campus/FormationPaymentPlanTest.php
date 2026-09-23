<?php

namespace Tests\Feature\Campus;

use App\Enums\CampusFormationMode;
use App\Enums\CohortStatus;
use App\Enums\PaymentStatus;
use App\Models\CampusFormation;
use App\Models\CampusFormationInstallment;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use Tests\TestCase;

class FormationPaymentPlanTest extends TestCase
{
    public function test_can_save_payment_plan_on_formation_without_learners(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->post('/campus/formations', [
                'name' => 'Dev Fullstack',
                'duration_months' => 6,
                'mode' => 'presentiel',
                'total_cost' => 100000,
                'is_active' => true,
                'installments' => [
                    ['type' => 'percentage', 'value' => 40, 'due_date' => '2026-10-01'],
                    ['type' => 'amount', 'value' => 30000, 'due_date' => '2026-11-01'],
                    ['type' => 'percentage', 'value' => 30, 'due_date' => '2026-12-01'],
                ],
            ])
            ->assertRedirect();

        $formation = CampusFormation::query()->where('name', 'Dev Fullstack')->first();
        $this->assertNotNull($formation);
        $this->assertSame(3, $formation->installments()->count());
    }

    public function test_enrolling_learner_creates_payments_from_plan(): void
    {
        $user = $this->createSuperAdmin();

        $formation = CampusFormation::create([
            'name' => 'Plan Auto',
            'slug' => 'plan-auto-'.uniqid(),
            'duration_months' => 6,
            'mode' => CampusFormationMode::Presentiel,
            'total_cost' => 100000,
            'is_active' => true,
        ]);

        CampusFormationInstallment::create([
            'campus_formation_id' => $formation->id,
            'position' => 1,
            'type' => 'percentage',
            'value' => 40,
            'due_date' => '2026-10-01',
        ]);
        CampusFormationInstallment::create([
            'campus_formation_id' => $formation->id,
            'position' => 2,
            'type' => 'percentage',
            'value' => 60,
            'due_date' => '2026-11-15',
        ]);

        $cohort = Cohort::create([
            'campus_formation_id' => $formation->id,
            'name' => 'C1',
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addMonths(6)->toDateString(),
            'capacity' => 20,
            'status' => CohortStatus::EnCours,
        ]);

        $learner = Learner::factory()->create();

        $this->actingAs($user)
            ->post("/campus/cohorts/{$cohort->id}/enroll", [
                'learner_ids' => [$learner->id],
            ])
            ->assertRedirect();

        $payments = Payment::query()
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->orderBy('installment_number')
            ->get();

        $this->assertCount(2, $payments);
        $this->assertSame(40000, $payments[0]->amount);
        $this->assertSame(60000, $payments[1]->amount);
        $this->assertSame(PaymentStatus::EnAttente, $payments[0]->status);
        $this->assertSame('2026-10-01', $payments[0]->due_date->toDateString());
        $this->assertSame('2026-11-15', $payments[1]->due_date->toDateString());
    }

    public function test_re_enroll_does_not_duplicate_payments(): void
    {
        $user = $this->createSuperAdmin();

        $formation = CampusFormation::create([
            'name' => 'Plan Dup',
            'slug' => 'plan-dup-'.uniqid(),
            'duration_months' => 3,
            'mode' => CampusFormationMode::Presentiel,
            'total_cost' => 50000,
            'is_active' => true,
        ]);

        CampusFormationInstallment::create([
            'campus_formation_id' => $formation->id,
            'position' => 1,
            'type' => 'amount',
            'value' => 50000,
            'due_date' => '2026-10-01',
        ]);

        $cohort = Cohort::create([
            'campus_formation_id' => $formation->id,
            'name' => 'C2',
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addMonths(3)->toDateString(),
            'capacity' => 10,
            'status' => CohortStatus::EnCours,
        ]);

        $learner = Learner::factory()->create();

        $this->actingAs($user)
            ->post("/campus/cohorts/{$cohort->id}/enroll", ['learner_ids' => [$learner->id]])
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/campus/cohorts/{$cohort->id}/enroll", ['learner_ids' => [$learner->id]])
            ->assertRedirect();

        $this->assertSame(1, Payment::query()
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->count());
    }
}
