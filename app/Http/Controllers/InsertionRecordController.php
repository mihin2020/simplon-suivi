<?php

namespace App\Http\Controllers;

use App\Enums\InsertionStatus;
use App\Models\InsertionRecord;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InsertionRecordController extends Controller
{
    public function index(Learner $learner): Response
    {
        $this->authorize('view', $learner);

        $records = $learner->insertionRecords()
            ->with('recorder')
            ->orderBy('status_changed_at', 'desc')
            ->get();

        return Inertia::render('Learners/Insertion', [
            'learner' => $learner->load('educationLevel'),
            'records' => $records,
            'statuses' => collect(InsertionStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'is_stage' => $s->isStage(),
                'is_employment' => $s->isEmployment(),
            ]),
        ]);
    }

    public function store(Request $request, Learner $learner): RedirectResponse
    {
        $this->authorize('update', $learner);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:searching,internship,employed,unemployed'],
            'status_notes' => ['nullable', 'string', 'max:1000'],
            
            // Stage fields
            'internship_start_date' => ['nullable', 'date', 'required_if:status,internship'],
            'internship_end_date' => ['nullable', 'date', 'after_or_equal:internship_start_date'],
            'internship_company' => ['nullable', 'string', 'max:255', 'required_if:status,internship'],
            'internship_paid' => ['nullable', 'boolean'],
            'internship_contract_type' => ['nullable', 'string', 'max:255'],
            
            // Employment fields
            'employment_company' => ['nullable', 'string', 'max:255', 'required_if:status,employed'],
            'employment_start_date' => ['nullable', 'date', 'required_if:status,employed'],
            'employment_contract_type' => ['nullable', 'string', 'in:CDI,CDD,freelance,autre'],
            'employment_position' => ['nullable', 'string', 'max:255'],
        ]);

        $record = new InsertionRecord([
            'learner_id' => $learner->id,
            'status' => InsertionStatus::from($validated['status']),
            'status_changed_at' => now(),
            'status_notes' => $validated['status_notes'] ?? null,
            
            'internship_start_date' => $validated['internship_start_date'] ?? null,
            'internship_end_date' => $validated['internship_end_date'] ?? null,
            'internship_company' => $validated['internship_company'] ?? null,
            'internship_paid' => $validated['internship_paid'] ?? null,
            'internship_contract_type' => $validated['internship_contract_type'] ?? null,
            
            'employment_company' => $validated['employment_company'] ?? null,
            'employment_start_date' => $validated['employment_start_date'] ?? null,
            'employment_contract_type' => $validated['employment_contract_type'] ?? null,
            'employment_position' => $validated['employment_position'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $record->save();

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Le statut d\'insertion a été enregistré avec succès.');
    }

    public function update(Request $request, Learner $learner, InsertionRecord $record): RedirectResponse
    {
        $this->authorize('update', $learner);

        if ($record->learner_id !== $learner->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:searching,internship,employed,unemployed'],
            'status_notes' => ['nullable', 'string', 'max:1000'],
            
            // Stage fields
            'internship_start_date' => ['nullable', 'date'],
            'internship_end_date' => ['nullable', 'date', 'after_or_equal:internship_start_date'],
            'internship_company' => ['nullable', 'string', 'max:255'],
            'internship_paid' => ['nullable', 'boolean'],
            'internship_contract_type' => ['nullable', 'string', 'max:255'],
            
            // Employment fields
            'employment_company' => ['nullable', 'string', 'max:255'],
            'employment_start_date' => ['nullable', 'date'],
            'employment_contract_type' => ['nullable', 'string', 'in:CDI,CDD,freelance,autre'],
            'employment_position' => ['nullable', 'string', 'max:255'],
        ]);

        $record->update([
            'status' => InsertionStatus::from($validated['status']),
            'status_notes' => $validated['status_notes'] ?? null,
            
            'internship_start_date' => $validated['internship_start_date'] ?? null,
            'internship_end_date' => $validated['internship_end_date'] ?? null,
            'internship_company' => $validated['internship_company'] ?? null,
            'internship_paid' => $validated['internship_paid'] ?? null,
            'internship_contract_type' => $validated['internship_contract_type'] ?? null,
            
            'employment_company' => $validated['employment_company'] ?? null,
            'employment_start_date' => $validated['employment_start_date'] ?? null,
            'employment_contract_type' => $validated['employment_contract_type'] ?? null,
            'employment_position' => $validated['employment_position'] ?? null,
        ]);

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Le statut d\'insertion a été mis à jour.');
    }

    public function destroy(Learner $learner, InsertionRecord $record): RedirectResponse
    {
        $this->authorize('update', $learner);

        if ($record->learner_id !== $learner->id) {
            abort(404);
        }

        $record->delete();

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'L\'entrée a été supprimée.');
    }
}
