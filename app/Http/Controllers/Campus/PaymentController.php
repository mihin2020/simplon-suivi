<?php

namespace App\Http\Controllers\Campus;

use App\Actions\Campus\CorrectPayment;
use App\Actions\Campus\GeneratePaymentSchedule;
use App\Actions\Campus\RecordPayment;
use App\Actions\Campus\RefundPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campus\GenerateGlobalPaymentScheduleRequest;
use App\Http\Requests\Campus\GeneratePaymentScheduleRequest;
use App\Http\Requests\Campus\MarkPaymentPaidRequest;
use App\Http\Requests\Campus\RefundPaymentRequest;
use App\Http\Requests\Campus\StorePaymentRequest;
use App\Http\Requests\Campus\UpdatePaymentRequest;
use App\Models\Cohort;
use App\Models\Payment;
use App\Services\Campus\CohortPaymentOverview;
use App\Services\Campus\PaymentReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    public function index(Request $request, Cohort $cohort, CohortPaymentOverview $overview): Response
    {
        return Inertia::render('Campus/Finance/Payments', [
            'cohort' => $cohort,
            ...$overview->for($cohort),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'icon' => $method->icon(),
            ]),
            // Editing an installment (amount/date correction) is reserved to admins.
            'canEdit' => $request->user()->can('correct', Payment::class),
        ]);
    }

    public function generateSchedule(GeneratePaymentScheduleRequest $request, Cohort $cohort, GeneratePaymentSchedule $action): RedirectResponse
    {
        $data = $request->validated();

        $action->forLearner($cohort, $data['learner_id'], $data['installments']);

        return back()->with('success', 'Échéancier de '.count($data['installments']).' tranche(s) enregistré.');
    }

    public function generateGlobalSchedule(GenerateGlobalPaymentScheduleRequest $request, Cohort $cohort, GeneratePaymentSchedule $action): RedirectResponse
    {
        $installments = $request->validated('installments');

        $learners = $action->forActiveLearners($cohort, $installments);

        return back()->with('success', 'Échéancier de '.count($installments)." tranche(s) appliqué à {$learners} apprenant(s).");
    }

    public function store(StorePaymentRequest $request, Cohort $cohort, RecordPayment $action): RedirectResponse
    {
        $action->execute($cohort, $request->validated());

        return back()->with('success', 'Versement enregistré.');
    }

    public function markPaid(MarkPaymentPaidRequest $request, Payment $payment): RedirectResponse
    {
        if (! in_array($payment->status, [PaymentStatus::EnAttente, PaymentStatus::EnRetard], true)) {
            return back()->withErrors(['payment' => 'Seule une tranche en attente ou en retard peut être encaissée.']);
        }

        $payment->update([
            'status' => PaymentStatus::Paye,
            'paid_at' => $request->validated('paid_at'),
            'payment_method' => $request->validated('payment_method'),
        ]);

        return back()->with('success', 'Paiement encaissé.');
    }

    public function update(UpdatePaymentRequest $request, Payment $payment, CorrectPayment $action): RedirectResponse
    {
        $action->execute($payment, $request->validated());

        return back()->with('success', 'Tranche mise à jour.');
    }

    public function refund(RefundPaymentRequest $request, Payment $payment, RefundPayment $action): RedirectResponse
    {
        $action->execute(
            $payment,
            (int) $request->validated('refund_amount'),
            $request->validated('refund_motif'),
            $request->user(),
        );

        return back()->with('success', 'Remboursement enregistré. Les montants ont été mis à jour.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        if ($payment->status === PaymentStatus::Rembourse) {
            return back()->withErrors(['payment' => 'Une tranche déjà remboursée ne peut pas être annulée.']);
        }

        $payment->update(['status' => PaymentStatus::Annule]);

        return back()->with('success', 'Tranche annulée.');
    }

    public function receipt(Request $request, Payment $payment, PaymentReceipt $receipt): HttpResponse
    {
        $this->ensureCollected($payment);

        return response(view('pdfs.receipt', $receipt->viewData($payment, $request->user()))->render(), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function receiptDownload(Request $request, Payment $payment, PaymentReceipt $receipt): SymfonyResponse
    {
        $this->ensureCollected($payment);

        $data = $receipt->viewData($payment, $request->user());

        return Pdf::loadView('pdfs.receipt-pdf', $data)
            ->setPaper('a4')
            ->download('recu-'.strtolower($data['receiptNumber']).'.pdf');
    }

    private function ensureCollected(Payment $payment): void
    {
        abort_unless($payment->status === PaymentStatus::Paye, 404, 'Reçu disponible uniquement pour les paiements encaissés.');
    }
}
