<?php

namespace App\Http\Controllers\Campus;

use App\Enums\CohortStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Cohort $cohort): Response
    {
        $cohort->load(['campusFormation' => fn ($q) => $q->withTrashed()]);
        $totalCost = $cohort->campusFormation?->total_cost ?? 0;

        $groupedPayments = $cohort->payments()
            ->orderBy('installment_number')
            ->get()
            ->groupBy('learner_id');

        $learners = $cohort->activeLearners()
            ->orderBy('last_name')
            ->get(['learners.id', 'first_name', 'last_name']);

        $learnerPayments = $learners->map(function ($learner) use ($groupedPayments, $totalCost) {
            $payments = $groupedPayments->get($learner->id, collect());
            $paidAmount = $payments->filter(fn ($p) => $p->status === PaymentStatus::Paye)->sum('amount');

            return [
                'learner' => $learner,
                'payments' => $payments->values(),
                'paid_amount' => $paidAmount,
                'remaining_amount' => max(0, $totalCost - $paidAmount),
                'progress' => $totalCost > 0 ? min(100, (int) round(($paidAmount / $totalCost) * 100)) : 0,
            ];
        });

        return Inertia::render('Campus/Finance/Payments', [
            'cohort' => $cohort,
            'total_cost' => $totalCost,
            'learnerPayments' => $learnerPayments,
            'stats' => [
                'total_expected' => $cohort->total_expected,
                'total_collected' => $cohort->total_collected,
                'total_remaining' => $cohort->total_remaining,
                'overdue_count' => $cohort->payments()->overdue()->distinct('learner_id')->count(),
            ],
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
                'icon' => $m->icon(),
            ]),
        ]);
    }

    public function generateSchedule(Request $request, Cohort $cohort): RedirectResponse
    {
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de planifier des paiements sur une cohorte clôturée.']);
        }

        $data = $request->validate([
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.amount' => ['required', 'integer', 'min:1'],
            'installments.*.due_date' => ['nullable', 'date'],
        ]);

        // Keep paid installments, replace pending/overdue ones
        $nextNum = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->where('status', PaymentStatus::Paye->value)
            ->count();

        Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->whereIn('status', [PaymentStatus::EnAttente->value, PaymentStatus::EnRetard->value])
            ->delete();

        foreach ($data['installments'] as $i => $installment) {
            Payment::create([
                'cohort_id' => $cohort->id,
                'learner_id' => $data['learner_id'],
                'amount' => $installment['amount'],
                'installment_number' => $nextNum + $i + 1,
                'due_date' => $installment['due_date'] ?? now()->addMonths($i + 1)->toDateString(),
                'status' => PaymentStatus::EnAttente,
            ]);
        }

        $count = count($data['installments']);

        return back()->with('success', "Échéancier de {$count} tranche(s) enregistré.");
    }

    public function generateGlobalSchedule(Request $request, Cohort $cohort): RedirectResponse
    {
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible de planifier des paiements sur une cohorte clôturée.']);
        }

        $data = $request->validate([
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.type' => ['required', 'in:amount,percentage'],
            'installments.*.value' => ['required', 'numeric', 'min:1'],
            'installments.*.due_date' => ['nullable', 'date'],
        ]);

        $cohort->load(['campusFormation' => fn ($q) => $q->withTrashed()]);
        $totalCost = $cohort->campusFormation?->total_cost ?? 0;

        $learners = $cohort->activeLearners()->get(['learners.id']);
        $n = $learners->count();

        if ($n === 0) {
            return back()->withErrors(['cohort' => 'Aucun apprenant actif dans cette cohorte.']);
        }

        foreach ($learners as $learner) {
            $nextNum = Payment::where('cohort_id', $cohort->id)
                ->where('learner_id', $learner->id)
                ->where('status', PaymentStatus::Paye->value)
                ->count();

            Payment::where('cohort_id', $cohort->id)
                ->where('learner_id', $learner->id)
                ->whereIn('status', [PaymentStatus::EnAttente->value, PaymentStatus::EnRetard->value])
                ->delete();

            foreach ($data['installments'] as $i => $installment) {
                $amount = $installment['type'] === 'percentage'
                    ? (int) round($installment['value'] / 100 * $totalCost)
                    : (int) $installment['value'];

                Payment::create([
                    'cohort_id' => $cohort->id,
                    'learner_id' => $learner->id,
                    'amount' => max(1, $amount),
                    'installment_number' => $nextNum + $i + 1,
                    'due_date' => $installment['due_date'] ?? now()->addMonths($i + 1)->toDateString(),
                    'status' => PaymentStatus::EnAttente,
                ]);
            }
        }

        $tranches = count($data['installments']);

        return back()->with('success', "Échéancier de {$tranches} tranche(s) appliqué à {$n} apprenant(s).");
    }

    public function store(Request $request, Cohort $cohort): RedirectResponse
    {
        if ($cohort->status === CohortStatus::Cloturee) {
            return back()->withErrors(['cohort' => 'Impossible d\'enregistrer un versement sur une cohorte clôturée.']);
        }

        $cohort->load(['campusFormation' => fn ($q) => $q->withTrashed()]);
        $totalCost = $cohort->campusFormation?->total_cost ?? 0;

        $data = $request->validate([
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['required', 'in:especes,mobile_money'],
            'notes' => ['nullable', 'string'],
        ]);

        $alreadyPaid = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->where('status', PaymentStatus::Paye->value)
            ->sum('amount');

        $remaining = $totalCost - $alreadyPaid;

        if ($remaining <= 0) {
            return back()->withErrors(['amount' => 'Cet apprenant a déjà réglé l\'intégralité des frais.']);
        }

        if ($data['amount'] > $remaining) {
            return back()->withErrors(['amount' => "Le montant dépasse le solde restant ({$remaining} FCFA)."]);
        }

        $nextNum = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->whereNotIn('status', [PaymentStatus::Annule->value])
            ->count() + 1;

        Payment::create([
            'cohort_id' => $cohort->id,
            'learner_id' => $data['learner_id'],
            'amount' => $data['amount'],
            'installment_number' => $nextNum,
            'due_date' => $data['paid_at'] ?? now()->toDateString(),
            'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            'status' => PaymentStatus::Paye,
            'payment_method' => $data['payment_method'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Versement enregistré.');
    }

    public function markPaid(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'in:especes,mobile_money'],
        ]);

        $payment->update([
            'status' => PaymentStatus::Paye,
            'paid_at' => $data['paid_at'],
            'payment_method' => $data['payment_method'],
        ]);

        return back()->with('success', 'Paiement encaissé.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->update(['status' => PaymentStatus::Annule]);

        return back()->with('success', 'Tranche annulée.');
    }

    public function receipt(Payment $payment): HttpResponse
    {
        if ($payment->status !== PaymentStatus::Paye) {
            abort(404, 'Reçu disponible uniquement pour les paiements encaissés.');
        }

        $payment->load(['learner', 'cohort' => fn ($q) => $q->with(['campusFormation' => fn ($q2) => $q2->withTrashed()])]);

        $receiptNumber = 'REC-'
            .($payment->paid_at ? $payment->paid_at->format('Ym') : now()->format('Ym'))
            .'-'
            .strtoupper(substr($payment->id, 0, 8));

        $html = view('pdfs.receipt', $this->receiptViewData($payment, $receiptNumber))->render();

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function receiptDownload(Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        if ($payment->status !== PaymentStatus::Paye) {
            abort(404, 'Reçu disponible uniquement pour les paiements encaissés.');
        }

        $payment->load(['learner', 'cohort' => fn ($q) => $q->with(['campusFormation' => fn ($q2) => $q2->withTrashed()])]);

        $receiptNumber = 'REC-'
            .($payment->paid_at ? $payment->paid_at->format('Ym') : now()->format('Ym'))
            .'-'
            .strtoupper(substr($payment->id, 0, 8));

        return Pdf::loadView('pdfs.receipt-pdf', $this->receiptViewData($payment, $receiptNumber))
            ->setPaper('a4')
            ->download('recu-'.strtolower($receiptNumber).'.pdf');
    }

    /** @return array<string, mixed> */
    private function receiptViewData(Payment $payment, string $receiptNumber): array
    {
        $cohort = $payment->cohort;
        $formation = $cohort->campusFormation;
        $learner = $payment->learner;
        $totalCost = $formation?->total_cost ?? 0;

        $totalPaid = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $learner->id)
            ->where('status', PaymentStatus::Paye->value)
            ->sum('amount');

        $remaining = max(0, $totalCost - $totalPaid);

        $methodLabels = [
            'especes' => 'Espèces',
            'mobile_money' => 'Mobile Money',
        ];

        $user = auth()->user();

        return [
            'payment' => $payment,
            'learner' => $learner,
            'cohort' => $cohort,
            'formation' => $formation,
            'totalCost' => $totalCost,
            'totalPaid' => $totalPaid,
            'remaining' => $remaining,
            'methodLabel' => $methodLabels[$payment->payment_method?->value ?? ''] ?? '—',
            'paidAt' => $payment->paid_at?->format('d/m/Y') ?? '—',
            'receiptNumber' => $receiptNumber,
            'issuedAt' => now()->format('d/m/Y à H:i'),
            'issuedBy' => $user ? trim("{$user->first_name} {$user->last_name}") : null,
        ];
    }
}
