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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CohortAbandonLearnerTest extends TestCase
{
    private function seedCohortWithLearner(): array
    {
        $formation = CampusFormation::create([
            'name' => 'Campus Abandon',
            'slug' => 'campus-abandon-'.uniqid(),
            'duration_months' => 6,
            'mode' => CampusFormationMode::Presentiel,
            'total_cost' => 100000,
            'is_active' => true,
        ]);

        $cohort = Cohort::create([
            'campus_formation_id' => $formation->id,
            'name' => 'Cohorte Abandon',
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

        return compact('formation', 'cohort', 'learner');
    }

    public function test_abandon_keeps_learner_visible_and_excludes_from_expected(): void
    {
        $user = $this->createSuperAdmin();
        ['cohort' => $cohort, 'learner' => $learner] = $this->seedCohortWithLearner();

        Payment::create([
            'cohort_id' => $cohort->id,
            'learner_id' => $learner->id,
            'amount' => 40000,
            'installment_number' => 1,
            'due_date' => now()->toDateString(),
            'paid_at' => now()->toDateString(),
            'status' => PaymentStatus::Paye,
            'payment_method' => PaymentMethod::Especes,
        ]);

        Payment::create([
            'cohort_id' => $cohort->id,
            'learner_id' => $learner->id,
            'amount' => 60000,
            'installment_number' => 2,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => PaymentStatus::EnAttente,
        ]);

        $this->assertSame(100000, $cohort->fresh()->total_expected);
        $this->assertSame(40000, $cohort->fresh()->total_collected);

        $this->actingAs($user)
            ->post("/campus/cohorts/{$cohort->id}/learners/{$learner->id}/abandon", [
                'abandon_motif' => 'Arrêt volontaire de la formation',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $pivot = DB::table('cohort_learner')
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->first();

        $this->assertSame('retrait', $pivot->status);
        $this->assertSame('Arrêt volontaire de la formation', $pivot->abandon_motif);
        $this->assertNotNull($pivot->abandoned_at);
        $this->assertTrue($cohort->learners()->where('learners.id', $learner->id)->exists());
        $this->assertSame(0, $cohort->fresh()->total_expected);
        $this->assertSame(40000, $cohort->fresh()->total_collected);

        $pending = Payment::query()
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->where('installment_number', 2)
            ->first();

        $this->assertSame(PaymentStatus::Annule, $pending->status);

        $paid = Payment::query()
            ->where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->where('installment_number', 1)
            ->first();

        $this->assertSame(PaymentStatus::Paye, $paid->status);
    }

    public function test_abandon_requires_motif(): void
    {
        $user = $this->createSuperAdmin();
        ['cohort' => $cohort, 'learner' => $learner] = $this->seedCohortWithLearner();

        $this->actingAs($user)
            ->post("/campus/cohorts/{$cohort->id}/learners/{$learner->id}/abandon", [
                'abandon_motif' => '',
            ])
            ->assertSessionHasErrors('abandon_motif');

        $this->assertSame(
            'actif',
            DB::table('cohort_learner')
                ->where('cohort_id', $cohort->id)
                ->where('learner_id', $learner->id)
                ->value('status')
        );
    }
}
