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
use Tests\TestCase;

class PaymentRefundTest extends TestCase
{
    private function seedPaidInstallment(int $amount = 50000): array
    {
        $formation = CampusFormation::create([
            'name' => 'Dev Web Campus',
            'slug' => 'dev-web-campus-'.uniqid(),
            'duration_months' => 6,
            'mode' => CampusFormationMode::Presentiel,
            'total_cost' => 150000,
            'is_active' => true,
        ]);

        $cohort = Cohort::create([
            'campus_formation_id' => $formation->id,
            'name' => 'Cohorte A',
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addMonths(6)->toDateString(),
            'capacity' => 20,
            'status' => CohortStatus::EnCours,
        ]);

        $learner = Learner::factory()->create();
        $cohort->learners()->attach($learner->id, [
            'enrolled_at' => now(),
            'status' => 'actif',
        ]);

        $payment = Payment::create([
            'cohort_id' => $cohort->id,
            'learner_id' => $learner->id,
            'amount' => $amount,
            'installment_number' => 1,
            'due_date' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'status' => PaymentStatus::Paye,
            'payment_method' => PaymentMethod::Especes,
        ]);

        return compact('formation', 'cohort', 'learner', 'payment');
    }

    public function test_refund_paid_installment_updates_status_and_totals(): void
    {
        $user = $this->createSuperAdmin();
        ['cohort' => $cohort, 'payment' => $payment] = $this->seedPaidInstallment(50000);

        $this->assertSame(50000, $cohort->fresh()->total_collected);

        $this->actingAs($user)
            ->post("/campus/payments/{$payment->id}/refund", [
                'refund_amount' => 30000,
                'refund_motif' => 'Abandon de formation',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Rembourse, $payment->status);
        $this->assertSame(30000, $payment->refund_amount);
        $this->assertSame('Abandon de formation', $payment->refund_motif);
        $this->assertSame($user->id, $payment->refunded_by);
        $this->assertNotNull($payment->refunded_at);

        $this->assertSame(0, $cohort->fresh()->total_collected);
    }

    public function test_refund_rejects_non_paid_installment(): void
    {
        $user = $this->createSuperAdmin();
        ['payment' => $payment] = $this->seedPaidInstallment();
        $payment->update(['status' => PaymentStatus::EnAttente, 'paid_at' => null]);

        $this->actingAs($user)
            ->post("/campus/payments/{$payment->id}/refund", [
                'refund_amount' => 1000,
                'refund_motif' => 'Test',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertSame(PaymentStatus::EnAttente, $payment->fresh()->status);
    }

    public function test_refund_requires_motif_and_valid_amount(): void
    {
        $user = $this->createSuperAdmin();
        ['payment' => $payment] = $this->seedPaidInstallment(20000);

        $this->actingAs($user)
            ->post("/campus/payments/{$payment->id}/refund", [
                'refund_amount' => 20000,
                'refund_motif' => '',
            ])
            ->assertSessionHasErrors('refund_motif');

        $this->actingAs($user)
            ->post("/campus/payments/{$payment->id}/refund", [
                'refund_amount' => 25000,
                'refund_motif' => 'Trop élevé',
            ])
            ->assertSessionHasErrors('refund_amount');

        $this->assertSame(PaymentStatus::Paye, $payment->fresh()->status);
    }

    public function test_cannot_cancel_refunded_installment(): void
    {
        $user = $this->createSuperAdmin();
        ['payment' => $payment] = $this->seedPaidInstallment();

        $payment->update([
            'status' => PaymentStatus::Rembourse,
            'refunded_at' => now(),
            'refund_amount' => $payment->amount,
            'refund_motif' => 'Déjà remboursé',
        ]);

        $this->actingAs($user)
            ->delete("/campus/payments/{$payment->id}")
            ->assertSessionHasErrors('payment');

        $this->assertSame(PaymentStatus::Rembourse, $payment->fresh()->status);
    }

    public function test_can_cancel_paid_installment(): void
    {
        $user = $this->createSuperAdmin();
        ['cohort' => $cohort, 'payment' => $payment] = $this->seedPaidInstallment(40000);

        $this->assertSame(40000, $cohort->fresh()->total_collected);

        $this->actingAs($user)
            ->delete("/campus/payments/{$payment->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Annule, $payment->fresh()->status);
        $this->assertSame(0, $cohort->fresh()->total_collected);
    }
}
