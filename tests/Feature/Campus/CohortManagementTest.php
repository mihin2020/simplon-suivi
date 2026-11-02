<?php

namespace Tests\Feature\Campus;

use App\Actions\Campus\ApplyFormationPaymentPlanToLearner;
use App\Enums\CampusFormationMode;
use App\Enums\CohortStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CampusFormation;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

/** Pins the behaviour of the campus cohort screens and actions. */
class CohortManagementTest extends TestCase
{
    private function formation(string $name = 'Campus Dev', int $cost = 100000): CampusFormation
    {
        return CampusFormation::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'duration_months' => 6,
            'mode' => CampusFormationMode::Presentiel,
            'total_cost' => $cost,
            'is_active' => true,
        ]);
    }

    private function cohort(?CampusFormation $formation = null, string $name = 'Cohorte A', CohortStatus $status = CohortStatus::EnCours): Cohort
    {
        return Cohort::create([
            'campus_formation_id' => ($formation ?? $this->formation())->id,
            'name' => $name,
            'started_at' => '2026-10-01',
            'ended_at' => '2027-03-31',
            'capacity' => 20,
            'status' => $status,
        ]);
    }

    private function enroll(Cohort $cohort, ?Learner $learner = null, string $status = 'actif'): Learner
    {
        $learner ??= Learner::factory()->create();
        $cohort->learners()->attach($learner->id, ['enrolled_at' => now(), 'status' => $status]);

        return $learner;
    }

    private function payment(Cohort $cohort, Learner $learner, PaymentStatus $status, int $amount, string $due = '2026-10-15'): Payment
    {
        return Payment::create([
            'cohort_id' => $cohort->id,
            'learner_id' => $learner->id,
            'amount' => $amount,
            'installment_number' => 1,
            'due_date' => $due,
            'paid_at' => $status === PaymentStatus::Paye ? $due : null,
            'status' => $status,
            'payment_method' => $status === PaymentStatus::Paye ? PaymentMethod::Especes : null,
        ]);
    }

    private function pivotStatus(Cohort $cohort, Learner $learner): ?string
    {
        return DB::table('cohort_learner')->where('cohort_id', $cohort->id)->where('learner_id', $learner->id)->value('status');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
    }

    // ── Screens ────────────────────────────────────────────────────────────

    public function test_the_index_lists_cohorts_newest_first_and_filters(): void
    {
        $dev = $this->formation('Dev');
        $design = $this->formation('Design');
        Cohort::create(['campus_formation_id' => $dev->id, 'name' => 'Ancienne', 'started_at' => '2026-01-01', 'ended_at' => '2026-06-01', 'status' => CohortStatus::Cloturee]);
        Cohort::create(['campus_formation_id' => $dev->id, 'name' => 'Recente', 'started_at' => '2026-10-01', 'ended_at' => '2027-03-01', 'status' => CohortStatus::EnCours]);
        Cohort::create(['campus_formation_id' => $design->id, 'name' => 'Autre', 'started_at' => '2026-05-01', 'ended_at' => '2026-12-01', 'status' => CohortStatus::Planifiee]);

        $this->get(route('campus.cohorts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campus/Cohorts/Index')
                ->has('cohorts.data', 3)
                ->where('cohorts.data.0.name', 'Recente')
                ->where('cohorts.data.2.name', 'Ancienne')
                ->where('filters', ['formation' => '', 'status' => ''])
                ->has('formations', 2)
                ->has('statuses', 3)
                ->etc());

        $this->get(route('campus.cohorts.index', ['formation' => $design->id]))
            ->assertInertia(fn (Assert $page) => $page->has('cohorts.data', 1)->where('cohorts.data.0.name', 'Autre')->etc());

        $this->get(route('campus.cohorts.index', ['status' => 'cloturee']))
            ->assertInertia(fn (Assert $page) => $page->has('cohorts.data', 1)->where('cohorts.data.0.name', 'Ancienne')->etc());
    }

    public function test_the_show_page_computes_the_payment_statistics(): void
    {
        $cohort = $this->cohort(null, 'Cohorte A');
        $paidInFull = $this->enroll($cohort);
        $partial = $this->enroll($cohort);
        $late = $this->enroll($cohort);

        $this->payment($cohort, $paidInFull, PaymentStatus::Paye, 100000);
        $this->payment($cohort, $partial, PaymentStatus::Paye, 40000);
        $this->payment($cohort, $partial, PaymentStatus::EnAttente, 60000, '2099-01-01');
        $this->payment($cohort, $late, PaymentStatus::EnAttente, 100000, '2020-01-01');

        $this->get(route('campus.cohorts.show', $cohort))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campus/Cohorts/Show')
                ->where('paymentStats.total_collected', 140000)
                ->where('paymentStats.total_expected', 300000)
                ->where('paymentStats.total_remaining', 160000)
                ->where('paymentStats.paid_count', 1)
                ->where('paymentStats.overdue_count', 1)
                ->has('learners.data', 3)
                ->etc());
    }

    public function test_the_show_page_offers_only_learners_not_enrolled_and_open_cohorts(): void
    {
        $cohort = $this->cohort(null, 'Cohorte A');
        $enrolled = $this->enroll($cohort);
        $free = Learner::factory()->create(['last_name' => 'Libre']);
        $open = $this->cohort(null, 'Ouverte');
        $this->cohort(null, 'Fermee', CohortStatus::Cloturee);

        $this->get(route('campus.cohorts.show', $cohort))
            ->assertInertia(function (Assert $page) use ($enrolled, $free, $open) {
                $props = $page->toArray()['props'];
                $available = array_column($props['availableLearners'], 'id');

                $this->assertContains($free->id, $available);
                $this->assertNotContains($enrolled->id, $available);
                $this->assertSame([$open->id], array_column($props['availableCohorts'], 'id'));
            });
    }

    // ── Create / update / delete ───────────────────────────────────────────

    public function test_a_cohort_is_created_and_validated(): void
    {
        $formation = $this->formation();

        $this->post(route('campus.cohorts.store'), [
            'campus_formation_id' => $formation->id, 'name' => 'Nouvelle', 'started_at' => '2026-11-01', 'ended_at' => '2027-04-01',
        ])->assertRedirect(route('campus.cohorts.index'))->assertSessionHas('success', 'Cohorte créée avec succès.');

        $this->assertDatabaseHas('cohorts', ['name' => 'Nouvelle', 'campus_formation_id' => $formation->id]);

        $this->post(route('campus.cohorts.store'), ['campus_formation_id' => 'x', 'name' => '', 'started_at' => '2026-11-01', 'ended_at' => '2026-10-01'])
            ->assertSessionHasErrors(['campus_formation_id', 'name', 'ended_at']);
    }

    public function test_a_cohort_is_updated_with_a_known_status_only(): void
    {
        $cohort = $this->cohort();

        $this->put(route('campus.cohorts.update', $cohort), [
            'name' => 'Renommee', 'started_at' => '2026-10-01', 'ended_at' => '2027-05-01', 'status' => 'planifiee',
        ])->assertRedirect(route('campus.cohorts.index'))->assertSessionHas('success', 'Cohorte mise à jour.');

        $this->assertSame('Renommee', $cohort->fresh()->name);
        $this->assertSame(CohortStatus::Planifiee, $cohort->fresh()->status);

        $this->put(route('campus.cohorts.update', $cohort), [
            'name' => 'x', 'started_at' => '2026-10-01', 'ended_at' => '2027-05-01', 'status' => 'inconnu',
        ])->assertSessionHasErrors('status');
    }

    public function test_a_cohort_is_deleted(): void
    {
        $cohort = $this->cohort();

        $this->delete(route('campus.cohorts.destroy', $cohort))->assertRedirect(route('campus.cohorts.index'))->assertSessionHas('success', 'Cohorte supprimée.');

        $this->assertSoftDeleted('cohorts', ['id' => $cohort->id]);
    }

    // ── Learners ───────────────────────────────────────────────────────────

    public function test_a_new_learner_is_created_and_enrolled_with_the_payment_plan_applied(): void
    {
        $cohort = $this->cohort();
        $this->mock(ApplyFormationPaymentPlanToLearner::class, fn (MockInterface $mock) => $mock->shouldReceive('execute')->once());

        $this->post(route('campus.cohorts.learners.store', $cohort), ['first_name' => 'Awa', 'last_name' => 'Zongo', 'email' => 'awa@example.test', 'phone' => '70000001'])
            ->assertSessionHas('success', 'Apprenant créé et inscrit dans la cohorte.');

        $learner = Learner::where('email', 'awa@example.test')->firstOrFail();
        $this->assertSame('actif', $this->pivotStatus($cohort, $learner));
    }

    public function test_a_photo_is_stored_for_a_new_learner(): void
    {
        Storage::fake('public');
        $cohort = $this->cohort();

        $this->post(route('campus.cohorts.learners.store', $cohort), [
            'first_name' => 'Awa', 'last_name' => 'Zongo', 'photo' => UploadedFile::fake()->image('p.jpg'),
        ])->assertSessionHas('success');

        $path = Learner::where('last_name', 'Zongo')->value('photo_path');
        $this->assertStringStartsWith('learners/photos/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_an_existing_email_enrolls_the_existing_learner_without_a_duplicate(): void
    {
        $cohort = $this->cohort();
        $existing = Learner::factory()->create(['email' => 'deja@example.test', 'first_name' => 'Deja', 'last_name' => 'Vu']);
        $this->mock(ApplyFormationPaymentPlanToLearner::class, fn (MockInterface $mock) => $mock->shouldReceive('execute')->once());

        $this->post(route('campus.cohorts.learners.store', $cohort), ['first_name' => 'Autre', 'last_name' => 'Nom', 'email' => 'deja@example.test'])
            ->assertSessionHas('success', "L'apprenant Deja Vu existe déjà et a été inscrit dans la cohorte.");

        $this->assertSame(1, Learner::where('email', 'deja@example.test')->count());
        $this->assertSame('actif', $this->pivotStatus($cohort, $existing));
    }

    public function test_an_already_enrolled_existing_learner_does_not_get_the_plan_twice(): void
    {
        $cohort = $this->cohort();
        $existing = $this->enroll($cohort, Learner::factory()->create(['email' => 'deja@example.test']));
        $this->mock(ApplyFormationPaymentPlanToLearner::class, fn (MockInterface $mock) => $mock->shouldReceive('execute')->never());

        $this->post(route('campus.cohorts.learners.store', $cohort), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'deja@example.test'])->assertSessionHas('success');

        $this->assertSame(1, $cohort->learners()->where('learners.id', $existing->id)->count());
    }

    public function test_enrolling_learners_applies_the_plan_only_to_the_new_ones(): void
    {
        $cohort = $this->cohort();
        $already = $this->enroll($cohort);
        $new = Learner::factory()->create();
        $this->mock(ApplyFormationPaymentPlanToLearner::class, fn (MockInterface $mock) => $mock->shouldReceive('execute')->once()->withArgs(fn ($c, $l) => $l->id === $new->id));

        $this->post(route('campus.cohorts.enroll', $cohort), ['learner_ids' => [$already->id, $new->id]])
            ->assertSessionHas('success', 'Apprenants inscrits avec succès.');

        $this->assertSame('actif', $this->pivotStatus($cohort, $new));
        $this->post(route('campus.cohorts.enroll', $cohort), ['learner_ids' => []])->assertSessionHasErrors('learner_ids');
        $this->post(route('campus.cohorts.enroll', $cohort), ['learner_ids' => ['pas-un-uuid']])->assertSessionHasErrors('learner_ids.0');
    }

    public function test_a_learner_is_updated_with_a_unique_email(): void
    {
        $cohort = $this->cohort();
        $learner = $this->enroll($cohort, Learner::factory()->create(['email' => 'a@example.test']));
        Learner::factory()->create(['email' => 'pris@example.test']);

        $this->post(route('campus.cohorts.learners.update', [$cohort, $learner]), ['first_name' => 'Nouveau', 'last_name' => 'Nom', 'email' => 'a@example.test'])
            ->assertSessionHas('success', "Informations de l'apprenant mises à jour.");
        $this->assertSame('Nouveau', $learner->fresh()->first_name);

        $this->post(route('campus.cohorts.learners.update', [$cohort, $learner]), ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'pris@example.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_learners_are_removed_one_by_one_or_in_bulk(): void
    {
        $cohort = $this->cohort();
        $one = $this->enroll($cohort);
        $two = $this->enroll($cohort);
        $three = $this->enroll($cohort);

        $this->delete(route('campus.cohorts.learners.remove', [$cohort, $one]))->assertSessionHas('success', 'Apprenant retiré de la cohorte.');
        $this->assertNull($this->pivotStatus($cohort, $one));

        $this->delete(route('campus.cohorts.learners.remove-bulk', $cohort), ['learner_ids' => [$two->id, $three->id]])
            ->assertSessionHas('success', '2 apprenant(s) retiré(s) de la cohorte.');
        $this->assertSame(0, $cohort->learners()->count());

        $this->delete(route('campus.cohorts.learners.remove-bulk', $cohort), ['learner_ids' => []])->assertSessionHasErrors('learner_ids');
    }

    public function test_every_change_of_a_closed_cohort_is_refused(): void
    {
        $cohort = $this->cohort(null, 'Fermee', CohortStatus::Cloturee);
        $learner = $this->enroll($cohort);
        $other = Learner::factory()->create();
        $message = 'Impossible de modifier une cohorte clôturée.';

        $this->post(route('campus.cohorts.learners.store', $cohort), ['first_name' => 'A', 'last_name' => 'B'])->assertSessionHasErrors(['cohort' => $message]);
        $this->post(route('campus.cohorts.enroll', $cohort), ['learner_ids' => [$other->id]])->assertSessionHasErrors(['cohort' => $message]);
        $this->post(route('campus.cohorts.import', $cohort), [])->assertSessionHasErrors(['cohort' => $message]);
        $this->delete(route('campus.cohorts.learners.remove', [$cohort, $learner]))->assertSessionHasErrors(['cohort' => $message]);
        $this->delete(route('campus.cohorts.learners.remove-bulk', $cohort), ['learner_ids' => [$learner->id]])->assertSessionHasErrors(['cohort' => $message]);
        $this->post(route('campus.cohorts.learners.abandon', [$cohort, $learner]), ['abandon_motif' => 'x'])->assertSessionHasErrors(['cohort' => $message]);

        $this->assertSame('actif', $this->pivotStatus($cohort, $learner));
        $this->assertNull($this->pivotStatus($cohort, $other));
    }

    public function test_abandoning_needs_a_reason(): void
    {
        $cohort = $this->cohort();
        $learner = $this->enroll($cohort);

        $this->post(route('campus.cohorts.learners.abandon', [$cohort, $learner]), [])
            ->assertSessionHasErrors(['abandon_motif' => "Le motif de l'abandon est obligatoire."]);
    }

    // ── Close / move ───────────────────────────────────────────────────────

    public function test_closing_a_cohort_graduates_its_active_learners(): void
    {
        $cohort = $this->cohort();
        $active = $this->enroll($cohort);
        $left = $this->enroll($cohort, null, 'retrait');

        $this->patch(route('campus.cohorts.close', $cohort))
            ->assertRedirect(route('campus.cohorts.show', $cohort))
            ->assertSessionHas('success', 'Cohorte clôturée. Les apprenants actifs ont été marqués « Formation terminée ».');

        $this->assertSame(CohortStatus::Cloturee, $cohort->fresh()->status);
        $this->assertSame('diplome', $this->pivotStatus($cohort, $active));
        $this->assertSame('retrait', $this->pivotStatus($cohort, $left));

        $this->patch(route('campus.cohorts.close', $cohort))->assertSessionHasErrors(['cohort' => 'Cette cohorte est déjà clôturée.']);
    }

    public function test_moving_a_learner_transfers_paid_payments_and_cancels_pending_ones(): void
    {
        $source = $this->cohort(null, 'Source');
        $target = $this->cohort(null, 'Cible');
        $learner = $this->enroll($source);
        $paid = $this->payment($source, $learner, PaymentStatus::Paye, 40000);
        $pending = $this->payment($source, $learner, PaymentStatus::EnAttente, 30000, '2099-01-01');
        $late = $this->payment($source, $learner, PaymentStatus::EnRetard, 30000, '2020-01-01');
        $other = $this->payment($source, Learner::factory()->create(), PaymentStatus::EnAttente, 10000);

        $this->post(route('campus.cohorts.learners.move', [$source, $learner]), ['target_cohort_id' => $target->id])
            ->assertSessionHas('success', "{$learner->first_name} {$learner->last_name} déplacé vers « Cible ». 1 versement(s) transféré(s). 2 tranche(s) en attente annulée(s) (à replanifier dans la nouvelle cohorte).");

        $this->assertSame('deplace', $this->pivotStatus($source, $learner));
        $this->assertSame('actif', $this->pivotStatus($target, $learner));
        $this->assertSame($target->id, $paid->fresh()->cohort_id);
        $this->assertSame(PaymentStatus::Paye, $paid->fresh()->status);
        $this->assertSame(PaymentStatus::Annule, $pending->fresh()->status);
        $this->assertSame(PaymentStatus::Annule, $late->fresh()->status);
        $this->assertSame($source->id, $pending->fresh()->cohort_id);
        $this->assertSame(PaymentStatus::EnAttente, $other->fresh()->status, "another learner's payments are untouched");
    }

    public function test_a_learner_already_in_the_target_cohort_is_not_duplicated_by_a_move(): void
    {
        $source = $this->cohort(null, 'Source');
        $target = $this->cohort(null, 'Cible');
        $learner = $this->enroll($source);
        $this->enroll($target, $learner, 'actif');

        $this->post(route('campus.cohorts.learners.move', [$source, $learner]), ['target_cohort_id' => $target->id])->assertSessionHas('success');

        $this->assertSame(1, DB::table('cohort_learner')->where('cohort_id', $target->id)->where('learner_id', $learner->id)->count());
    }

    public function test_an_invalid_move_is_refused_and_changes_nothing(): void
    {
        $source = $this->cohort(null, 'Source');
        $target = $this->cohort(null, 'Cible');
        $closed = $this->cohort(null, 'Fermee', CohortStatus::Cloturee);
        $active = $this->enroll($source);
        $left = $this->enroll($source, null, 'retrait');
        $url = fn (Learner $learner) => route('campus.cohorts.learners.move', [$source, $learner]);

        $this->post($url($active), ['target_cohort_id' => $source->id])->assertSessionHasErrors(['target_cohort_id' => 'La cohorte cible doit être différente de la cohorte source.']);
        $this->post($url($left), ['target_cohort_id' => $target->id])->assertSessionHasErrors(['learner' => "Cet apprenant n'est pas actif dans cette cohorte."]);
        $this->post($url(Learner::factory()->create()), ['target_cohort_id' => $target->id])->assertSessionHasErrors('learner');
        $this->post($url($active), ['target_cohort_id' => $closed->id])->assertSessionHasErrors(['target_cohort_id' => 'La cohorte cible est clôturée.']);
        $this->post($url($active), ['target_cohort_id' => 'pas-un-uuid'])->assertSessionHasErrors('target_cohort_id');

        $this->assertSame('actif', $this->pivotStatus($source, $active));
        $this->assertNull($this->pivotStatus($target, $active));
    }

    // ── Access ─────────────────────────────────────────────────────────────

    public function test_cohort_screens_and_actions_need_their_permissions(): void
    {
        $cohort = $this->cohort();
        $learner = $this->enroll($cohort);
        $this->actingAsTrainer();

        $this->get(route('campus.cohorts.index'))->assertForbidden();
        $this->get(route('campus.cohorts.show', $cohort))->assertForbidden();
        $this->post(route('campus.cohorts.store'), [])->assertForbidden();
        $this->put(route('campus.cohorts.update', $cohort), [])->assertForbidden();
        $this->delete(route('campus.cohorts.destroy', $cohort))->assertForbidden();
        $this->patch(route('campus.cohorts.close', $cohort))->assertForbidden();
        $this->post(route('campus.cohorts.learners.move', [$cohort, $learner]), [])->assertForbidden();
    }
}
