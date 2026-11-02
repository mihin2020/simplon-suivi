<?php

namespace Tests\Feature\Campus;

use App\Enums\CampusFormationMode;
use App\Enums\CohortStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CampusFormation;
use App\Models\CampusFormationInstallment;
use App\Models\Cohort;
use App\Models\Learner;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Pins the money rules of the campus finance screens: schedules, payments, corrections and receipts. */
class PaymentManagementTest extends TestCase
{
    private Cohort $cohort;

    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 10:00:00');

        $formation = CampusFormation::create([
            'name' => 'Campus Dev', 'slug' => 'campus-dev-'.uniqid(), 'duration_months' => 6,
            'mode' => CampusFormationMode::Presentiel, 'total_cost' => 100000, 'is_active' => true,
        ]);
        CampusFormationInstallment::create(['campus_formation_id' => $formation->id, 'position' => 2, 'type' => 'percentage', 'value' => 60, 'due_date' => '2026-12-01']);
        CampusFormationInstallment::create(['campus_formation_id' => $formation->id, 'position' => 1, 'type' => 'amount', 'value' => 40000, 'due_date' => '2026-11-01']);

        $this->cohort = Cohort::create(['campus_formation_id' => $formation->id, 'name' => 'Cohorte A', 'started_at' => '2026-10-01', 'ended_at' => '2027-03-31', 'status' => CohortStatus::EnCours]);
        $this->learner = $this->enroll();
        $this->actingAsSuperAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function enroll(string $status = 'actif'): Learner
    {
        $learner = Learner::factory()->create();
        $this->cohort->learners()->attach($learner->id, ['enrolled_at' => now(), 'status' => $status]);

        return $learner;
    }

    private function payment(PaymentStatus $status, int $amount, ?Learner $learner = null, int $number = 1, string $due = '2026-11-15'): Payment
    {
        return Payment::create([
            'cohort_id' => $this->cohort->id,
            'learner_id' => ($learner ?? $this->learner)->id,
            'amount' => $amount,
            'installment_number' => $number,
            'due_date' => $due,
            'paid_at' => $status === PaymentStatus::Paye ? $due : null,
            'status' => $status,
            'payment_method' => $status === PaymentStatus::Paye ? PaymentMethod::Especes : null,
        ]);
    }

    private function actingAsFinanceUser(string $role = 'admin'): User
    {
        $user = $role === 'admin' ? $this->createAdmin() : $this->createTrainer();
        $user->permissions()->attach(Permission::firstOrCreate(['slug' => 'campus.finance.collect'], ['name' => 'collect', 'group' => 'Test'])->id);
        $this->actingAs($user);

        return $user;
    }

    // ── Overview ───────────────────────────────────────────────────────────

    public function test_the_overview_lists_active_and_abandoned_learners_with_their_amounts(): void
    {
        $abandoned = $this->enroll('retrait');
        $this->enroll('deplace');
        $this->payment(PaymentStatus::Paye, 40000);
        $this->payment(PaymentStatus::EnAttente, 60000, null, 2, '2020-01-01');
        $this->payment(PaymentStatus::Paye, 10000, $abandoned);

        $this->get(route('campus.payments.index', $this->cohort))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campus/Finance/Payments')
                ->where('total_cost', 100000)
                ->has('learnerPayments', 2)
                ->where('learnerPayments.0.paid_amount', fn ($v) => in_array($v, [40000, 10000]))
                ->etc());

        $props = $this->get(route('campus.payments.index', $this->cohort))->viewData('page')['props'];
        $byLearner = collect($props['learnerPayments'])->keyBy(fn ($row) => $row['learner']['id']);

        $active = $byLearner[$this->learner->id];
        $this->assertSame(40000, $active['paid_amount']);
        $this->assertSame(60000, $active['remaining_amount']);
        $this->assertSame(40, $active['progress']);
        $this->assertFalse($active['is_abandoned']);
        $this->assertCount(2, $active['payments']);

        $gone = $byLearner[$abandoned->id];
        $this->assertTrue($gone['is_abandoned']);
        $this->assertSame(0, $gone['remaining_amount'], 'fees of an abandoned learner are no longer due');
        $this->assertSame(0, $gone['progress']);

        $this->assertSame([
            'total_expected' => 100000, 'total_collected' => 50000, 'total_remaining' => 50000, 'overdue_count' => 1,
        ], $props['stats']);
        $this->assertSame([['position' => 1, 'type' => 'amount', 'value' => 40000, 'due_date' => '2026-11-01'], ['position' => 2, 'type' => 'percentage', 'value' => 60, 'due_date' => '2026-12-01']], $props['formationPlan']);
        $this->assertTrue($props['canEdit']);
        $this->assertCount(2, $props['paymentMethods']);
    }

    public function test_only_administrators_can_correct_installments(): void
    {
        $this->actingAsFinanceUser('trainer');

        $props = $this->get(route('campus.payments.index', $this->cohort))->viewData('page')['props'];
        $this->assertFalse($props['canEdit']);

        $this->actingAsFinanceUser('admin');
        $this->assertTrue($this->get(route('campus.payments.index', $this->cohort))->viewData('page')['props']['canEdit']);
    }

    // ── Schedules ──────────────────────────────────────────────────────────

    public function test_a_schedule_replaces_the_pending_installments_and_keeps_the_paid_ones(): void
    {
        $this->payment(PaymentStatus::Paye, 30000, null, 1);
        $this->payment(PaymentStatus::Rembourse, 5000, null, 2);
        $this->payment(PaymentStatus::EnAttente, 20000, null, 3);
        $this->payment(PaymentStatus::EnRetard, 20000, null, 4);
        $annule = $this->payment(PaymentStatus::Annule, 1000, null, 5);

        $this->post(route('campus.payments.schedule', $this->cohort), [
            'learner_id' => $this->learner->id,
            'installments' => [['amount' => 25000, 'due_date' => '2026-12-10'], ['amount' => 45000]],
        ])->assertSessionHas('success', 'Échéancier de 2 tranche(s) enregistré.');

        $pending = Payment::where('learner_id', $this->learner->id)->where('status', PaymentStatus::EnAttente->value)->orderBy('installment_number')->get();
        $this->assertCount(2, $pending);
        $this->assertSame([3, 4], $pending->pluck('installment_number')->all(), 'numbered after the 2 paid/refunded ones');
        $this->assertSame([25000, 45000], $pending->pluck('amount')->all());
        $this->assertSame('2026-12-10', $pending[0]->due_date->toDateString());
        $this->assertSame('2027-01-02', $pending[1]->due_date->toDateString(), 'default: i+1 months from today, the 2nd installment is 2 months away');
        $this->assertSame(0, Payment::where('status', PaymentStatus::EnRetard->value)->count());
        $this->assertSame(1, Payment::where('status', PaymentStatus::Paye->value)->count());
        $this->assertSame(1, Payment::where('status', PaymentStatus::Rembourse->value)->count());
        $this->assertNotNull(Payment::find($annule->id));
    }

    public function test_a_schedule_is_validated(): void
    {
        $url = route('campus.payments.schedule', $this->cohort);

        $this->post($url, ['learner_id' => $this->learner->id, 'installments' => []])->assertSessionHasErrors('installments');
        $this->post($url, ['learner_id' => 'x', 'installments' => [['amount' => 1]]])->assertSessionHasErrors('learner_id');
        $this->post($url, ['learner_id' => $this->learner->id, 'installments' => [['amount' => 0]]])->assertSessionHasErrors('installments.0.amount');
        $this->post($url, ['learner_id' => $this->learner->id, 'installments' => array_fill(0, 25, ['amount' => 1])])->assertSessionHasErrors('installments');
        $this->assertSame(0, Payment::count());
    }

    public function test_a_global_schedule_applies_amounts_and_percentages_to_every_active_learner(): void
    {
        $second = $this->enroll();
        $this->enroll('retrait');
        $this->payment(PaymentStatus::Paye, 10000, $second, 1);
        $this->payment(PaymentStatus::EnAttente, 90000, $second, 2);

        $this->post(route('campus.payments.schedule-global', $this->cohort), [
            'installments' => [['type' => 'percentage', 'value' => 30, 'due_date' => '2026-12-01'], ['type' => 'amount', 'value' => 70000]],
        ])->assertSessionHas('success', 'Échéancier de 2 tranche(s) appliqué à 2 apprenant(s).');

        foreach ([$this->learner, $second] as $learner) {
            $pending = Payment::where('learner_id', $learner->id)->where('status', PaymentStatus::EnAttente->value)->orderBy('installment_number')->get();
            $this->assertSame([30000, 70000], $pending->pluck('amount')->all());
            $this->assertSame('2026-12-01', $pending[0]->due_date->toDateString());
            $this->assertSame('2027-01-02', $pending[1]->due_date->toDateString());
        }
        $this->assertSame([2, 3], Payment::where('learner_id', $second->id)->where('status', PaymentStatus::EnAttente->value)->orderBy('installment_number')->pluck('installment_number')->all());
        $this->assertSame(0, Payment::where('learner_id', '!=', $this->learner->id)->where('learner_id', '!=', $second->id)->count(), 'abandoned learners are skipped');
    }

    public function test_a_global_schedule_needs_active_learners(): void
    {
        $this->cohort->learners()->detach();

        $this->post(route('campus.payments.schedule-global', $this->cohort), [
            'installments' => [['type' => 'amount', 'value' => 1000]],
        ])->assertSessionHasErrors(['cohort' => 'Aucun apprenant actif dans cette cohorte.']);
    }

    public function test_a_global_schedule_is_validated(): void
    {
        $this->post(route('campus.payments.schedule-global', $this->cohort), ['installments' => [['type' => 'bonus', 'value' => 5]]])
            ->assertSessionHasErrors('installments.0.type');
    }

    // ── Payments ───────────────────────────────────────────────────────────

    public function test_a_payment_is_recorded_and_numbered(): void
    {
        $this->payment(PaymentStatus::Paye, 20000, null, 1);
        $this->payment(PaymentStatus::Annule, 5000, null, 2);

        $this->post(route('campus.payments.store', $this->cohort), [
            'learner_id' => $this->learner->id, 'amount' => 30000, 'payment_method' => 'mobile_money', 'notes' => 'Orange', 'paid_at' => '2026-11-01',
        ])->assertSessionHas('success', 'Versement enregistré.');

        $created = Payment::where('amount', 30000)->firstOrFail();
        $this->assertSame(2, $created->installment_number, 'cancelled installments are not counted');
        $this->assertSame(PaymentStatus::Paye, $created->status);
        $this->assertSame(PaymentMethod::MobileMoney, $created->payment_method);
        $this->assertSame('2026-11-01', $created->paid_at->toDateString());
        $this->assertSame('2026-11-01', $created->due_date->toDateString());
        $this->assertSame('Orange', $created->notes);

        $this->post(route('campus.payments.store', $this->cohort), ['learner_id' => $this->learner->id, 'amount' => 1000, 'payment_method' => 'especes']);
        $this->assertSame('2026-11-02', Payment::where('amount', 1000)->firstOrFail()->paid_at->toDateString(), 'defaults to today');
    }

    public function test_a_payment_cannot_exceed_the_remaining_balance(): void
    {
        $this->payment(PaymentStatus::Paye, 80000);
        $url = route('campus.payments.store', $this->cohort);

        $this->post($url, ['learner_id' => $this->learner->id, 'amount' => 20001, 'payment_method' => 'especes'])
            ->assertSessionHasErrors(['amount' => 'Le montant dépasse le solde restant (20000 FCFA).']);

        $this->post($url, ['learner_id' => $this->learner->id, 'amount' => 20000, 'payment_method' => 'especes'])->assertSessionHas('success');

        $this->post($url, ['learner_id' => $this->learner->id, 'amount' => 1, 'payment_method' => 'especes'])
            ->assertSessionHasErrors(['amount' => "Cet apprenant a déjà réglé l'intégralité des frais."]);
    }

    public function test_a_payment_is_validated(): void
    {
        $url = route('campus.payments.store', $this->cohort);

        $this->post($url, ['learner_id' => $this->learner->id, 'amount' => 5, 'payment_method' => 'cheque'])->assertSessionHasErrors('payment_method');
        $this->post($url, ['learner_id' => $this->learner->id, 'amount' => 0, 'payment_method' => 'especes'])->assertSessionHasErrors('amount');
        $this->post($url, ['amount' => 5, 'payment_method' => 'especes'])->assertSessionHasErrors('learner_id');
    }

    public function test_a_closed_cohort_accepts_no_payment_and_no_schedule(): void
    {
        $this->cohort->update(['status' => CohortStatus::Cloturee]);

        $this->post(route('campus.payments.schedule', $this->cohort), ['learner_id' => $this->learner->id, 'installments' => [['amount' => 1]]])
            ->assertSessionHasErrors(['cohort' => 'Impossible de planifier des paiements sur une cohorte clôturée.']);
        $this->post(route('campus.payments.schedule-global', $this->cohort), ['installments' => [['type' => 'amount', 'value' => 1]]])
            ->assertSessionHasErrors(['cohort' => 'Impossible de planifier des paiements sur une cohorte clôturée.']);
        $this->post(route('campus.payments.store', $this->cohort), ['learner_id' => $this->learner->id, 'amount' => 1, 'payment_method' => 'especes'])
            ->assertSessionHasErrors(['cohort' => "Impossible d'enregistrer un versement sur une cohorte clôturée."]);

        $this->assertSame(0, Payment::count());
    }

    public function test_a_pending_installment_can_be_collected_but_not_a_paid_one(): void
    {
        $pending = $this->payment(PaymentStatus::EnAttente, 10000);
        $late = $this->payment(PaymentStatus::EnRetard, 10000, null, 2);
        $paid = $this->payment(PaymentStatus::Paye, 10000, null, 3);

        $this->patch(route('campus.payments.mark-paid', $pending), ['paid_at' => '2026-11-02', 'payment_method' => 'especes'])->assertSessionHas('success', 'Paiement encaissé.');
        $this->patch(route('campus.payments.mark-paid', $late), ['paid_at' => '2026-11-02', 'payment_method' => 'mobile_money'])->assertSessionHas('success');
        $this->patch(route('campus.payments.mark-paid', $paid), ['paid_at' => '2026-11-02', 'payment_method' => 'especes'])
            ->assertSessionHasErrors(['payment' => 'Seule une tranche en attente ou en retard peut être encaissée.']);

        $this->assertSame(PaymentStatus::Paye, $pending->fresh()->status);
        $this->assertSame(PaymentMethod::MobileMoney, $late->fresh()->payment_method);
        $this->patch(route('campus.payments.mark-paid', $pending), [])->assertSessionHasErrors(['paid_at', 'payment_method']);
    }

    // ── Corrections and cancellation ───────────────────────────────────────

    public function test_an_administrator_corrects_an_installment_within_the_budget(): void
    {
        $pending = $this->payment(PaymentStatus::EnAttente, 50000);
        $this->payment(PaymentStatus::Paye, 30000, null, 2);
        $this->payment(PaymentStatus::Annule, 99999, null, 3);

        $this->patch(route('campus.payments.update', $pending), ['amount' => 70000, 'due_date' => '2026-12-20'])
            ->assertSessionHas('success', 'Tranche mise à jour.');
        $this->assertSame(70000, $pending->fresh()->amount);
        $this->assertSame('2026-12-20', $pending->fresh()->due_date->toDateString());

        $this->patch(route('campus.payments.update', $pending), ['amount' => 70001, 'due_date' => '2026-12-20'])
            ->assertSessionHasErrors(['amount' => 'Le montant dépasse le solde planifiable (70000 FCFA).']);
        $this->assertSame(70000, $pending->fresh()->amount);
    }

    public function test_correcting_a_paid_installment_requires_its_collection_date(): void
    {
        $paid = $this->payment(PaymentStatus::Paye, 30000);

        $this->patch(route('campus.payments.update', $paid), ['amount' => 30000, 'due_date' => '2026-11-15'])->assertSessionHasErrors('paid_at');
        $this->patch(route('campus.payments.update', $paid), ['amount' => 30000, 'due_date' => '2026-11-15', 'paid_at' => '2026-11-03'])->assertSessionHas('success');
        $this->assertSame('2026-11-03', $paid->fresh()->paid_at->toDateString());

        $pending = $this->payment(PaymentStatus::EnAttente, 1000, null, 2);
        $this->patch(route('campus.payments.update', $pending), ['amount' => 1000, 'due_date' => '2026-11-15', 'paid_at' => '2026-01-01']);
        $this->assertNull($pending->fresh()->paid_at, 'a pending installment never gets a collection date');
    }

    public function test_refunded_and_cancelled_installments_cannot_be_corrected(): void
    {
        foreach ([PaymentStatus::Rembourse, PaymentStatus::Annule] as $i => $status) {
            $payment = $this->payment($status, 1000, null, $i + 1);

            $this->patch(route('campus.payments.update', $payment), ['amount' => 5, 'due_date' => '2026-11-15'])
                ->assertSessionHasErrors(['payment' => 'Une tranche remboursée ou annulée ne peut pas être modifiée.']);
            $this->assertSame(1000, $payment->fresh()->amount);
        }
    }

    public function test_a_collector_who_is_not_an_administrator_cannot_correct(): void
    {
        $pending = $this->payment(PaymentStatus::EnAttente, 1000);
        $this->actingAsFinanceUser('trainer');

        $this->patch(route('campus.payments.update', $pending), ['amount' => 5, 'due_date' => '2026-11-15'])->assertForbidden();
        $this->assertSame(1000, $pending->fresh()->amount);
    }

    public function test_an_installment_is_cancelled_unless_already_refunded(): void
    {
        $pending = $this->payment(PaymentStatus::EnAttente, 1000);
        $refunded = $this->payment(PaymentStatus::Rembourse, 1000, null, 2);

        $this->delete(route('campus.payments.destroy', $pending))->assertSessionHas('success', 'Tranche annulée.');
        $this->assertSame(PaymentStatus::Annule, $pending->fresh()->status);

        $this->delete(route('campus.payments.destroy', $refunded))
            ->assertSessionHasErrors(['payment' => 'Une tranche déjà remboursée ne peut pas être annulée.']);
        $this->assertSame(PaymentStatus::Rembourse, $refunded->fresh()->status);
    }

    // ── Receipts ───────────────────────────────────────────────────────────

    public function test_a_receipt_exists_only_for_collected_payments(): void
    {
        $pending = $this->payment(PaymentStatus::EnAttente, 1000);

        $this->get(route('campus.payments.receipt', $pending))->assertNotFound();
        $this->get(route('campus.payments.receipt.download', $pending))->assertNotFound();
    }

    public function test_the_receipt_page_shows_the_numbered_receipt(): void
    {
        $paid = $this->payment(PaymentStatus::Paye, 40000);
        $number = 'REC-202611-'.strtoupper(substr($paid->id, 0, 8));

        $response = $this->get(route('campus.payments.receipt', $paid))->assertOk();

        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $response->assertSee($number);
        $response->assertSee('Espèces');
    }

    public function test_the_receipt_can_be_downloaded_as_a_pdf(): void
    {
        $paid = $this->payment(PaymentStatus::Paye, 40000);
        $number = strtolower('REC-202611-'.strtoupper(substr($paid->id, 0, 8)));

        $response = $this->get(route('campus.payments.receipt.download', $paid))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("recu-{$number}.pdf", $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // ── Access ─────────────────────────────────────────────────────────────

    public function test_the_finance_screens_need_a_finance_permission(): void
    {
        $paid = $this->payment(PaymentStatus::Paye, 1000);
        $this->actingAsTrainer();

        $this->get(route('campus.payments.index', $this->cohort))->assertForbidden();
        $this->post(route('campus.payments.store', $this->cohort), [])->assertForbidden();
        $this->patch(route('campus.payments.mark-paid', $paid), [])->assertForbidden();
        $this->delete(route('campus.payments.destroy', $paid))->assertForbidden();
        $this->get(route('campus.payments.receipt', $paid))->assertForbidden();
    }
}
