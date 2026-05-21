<?php

namespace App\Http\Controllers\Campus;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Cohort $cohort): Response
    {
        $cohort->load('campusFormation');
        $totalCost = $cohort->campusFormation->total_cost;

        $groupedPayments = $cohort->payments()
            ->orderBy('installment_number')
            ->get()
            ->groupBy('learner_id');

        $learners = $cohort->activeLearners()
            ->orderBy('last_name')
            ->get(['learners.id', 'first_name', 'last_name']);

        $learnerPayments = $learners->map(function ($learner) use ($groupedPayments, $totalCost) {
            $payments   = $groupedPayments->get($learner->id, collect());
            $paidAmount = $payments->where('status', PaymentStatus::Paye->value)->sum('amount');

            return [
                'learner'          => $learner,
                'payments'         => $payments->values(),
                'paid_amount'      => $paidAmount,
                'remaining_amount' => max(0, $totalCost - $paidAmount),
                'progress'         => $totalCost > 0 ? min(100, (int) round(($paidAmount / $totalCost) * 100)) : 0,
            ];
        });

        return Inertia::render('Campus/Finance/Payments', [
            'cohort'          => $cohort,
            'total_cost'      => $totalCost,
            'learnerPayments' => $learnerPayments,
            'stats'           => [
                'total_expected'  => $cohort->total_expected,
                'total_collected' => $cohort->total_collected,
                'total_remaining' => $cohort->total_remaining,
                'overdue_count'   => $cohort->payments()->overdue()->distinct('learner_id')->count(),
            ],
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn($m) => [
                'value' => $m->value,
                'label' => $m->label(),
                'icon'  => $m->icon(),
            ]),
        ]);
    }

    public function generateSchedule(Request $request, Cohort $cohort): RedirectResponse
    {
        $data = $request->validate([
            'learner_id'              => ['required', 'uuid', 'exists:learners,id'],
            'installments'            => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.amount'   => ['required', 'integer', 'min:1'],
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
                'cohort_id'          => $cohort->id,
                'learner_id'         => $data['learner_id'],
                'amount'             => $installment['amount'],
                'installment_number' => $nextNum + $i + 1,
                'due_date'           => $installment['due_date'] ?? now()->addMonths($i + 1)->toDateString(),
                'status'             => PaymentStatus::EnAttente,
            ]);
        }

        $count = count($data['installments']);

        return back()->with('success', "Échéancier de {$count} tranche(s) enregistré.");
    }

    public function store(Request $request, Cohort $cohort): RedirectResponse
    {
        $data = $request->validate([
            'learner_id' => ['required', 'uuid', 'exists:learners,id'],
            'amount'     => ['required', 'integer', 'min:1'],
            'due_date'   => ['nullable', 'date'],
            'notes'      => ['nullable', 'string'],
        ]);

        $nextNum = Payment::where('cohort_id', $cohort->id)
            ->where('learner_id', $data['learner_id'])
            ->whereNotIn('status', [PaymentStatus::Annule->value])
            ->count() + 1;

        Payment::create([
            'cohort_id'          => $cohort->id,
            'learner_id'         => $data['learner_id'],
            'amount'             => $data['amount'],
            'installment_number' => $nextNum,
            'due_date'           => $data['due_date'] ?? now()->addMonth()->toDateString(),
            'status'             => PaymentStatus::EnAttente,
            'notes'              => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Tranche ajoutée.');
    }

    public function markPaid(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'paid_at'        => ['required', 'date'],
            'payment_method' => ['required', 'in:especes,mobile_money'],
        ]);

        $payment->update([
            'status'         => PaymentStatus::Paye,
            'paid_at'        => $data['paid_at'],
            'payment_method' => $data['payment_method'],
        ]);

        return back()->with('success', 'Paiement encaissé.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->update(['status' => PaymentStatus::Annule]);

        return back()->with('success', 'Tranche annulée.');
    }
}
