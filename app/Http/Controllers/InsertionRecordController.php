<?php

namespace App\Http\Controllers;

use App\Actions\Insertion\CreateInsertionRecord;
use App\Actions\Insertion\DeleteInsertionRecord;
use App\Actions\Insertion\UpdateInsertionRecord;
use App\Enums\InsertionStatus;
use App\Http\Requests\Insertion\StoreInsertionRecordRequest;
use App\Http\Requests\Insertion\UpdateInsertionRecordRequest;
use App\Models\InsertionRecord;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsertionRecordController extends Controller
{
    public function index(Learner $learner): Response
    {
        $this->authorize('view', $learner);

        return Inertia::render('Learners/Insertion', [
            'learner' => $learner->load('educationLevel'),
            'records' => $learner->insertionRecords()->with('recorder')->orderBy('status_changed_at', 'desc')->get(),
            'statuses' => collect(InsertionStatus::cases())->map(fn (InsertionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
                'is_stage' => $status->isStage(),
                'is_employment' => $status->isEmployment(),
            ]),
        ]);
    }

    public function store(StoreInsertionRecordRequest $request, Learner $learner, CreateInsertionRecord $action): RedirectResponse
    {
        $action->execute(
            learner: $learner,
            attributes: $request->recordAttributes(),
            statusChangedAt: $request->validated('status_changed_at'),
            recordedBy: $request->user()->id,
            internshipContract: $request->file('internship_contract_file'),
            employmentContract: $request->file('employment_contract_file'),
        );

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Le statut d\'insertion a été enregistré avec succès.');
    }

    public function update(
        UpdateInsertionRecordRequest $request,
        Learner $learner,
        InsertionRecord $record,
        UpdateInsertionRecord $action,
    ): RedirectResponse {
        $this->ensureBelongsTo($learner, $record);

        $action->execute(
            record: $record,
            attributes: $request->recordAttributes(),
            internshipContract: $request->file('internship_contract_file'),
            employmentContract: $request->file('employment_contract_file'),
            removeInternshipContract: $request->boolean('remove_internship_contract'),
            removeEmploymentContract: $request->boolean('remove_employment_contract'),
        );

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'Le statut d\'insertion a été mis à jour.');
    }

    public function destroy(Learner $learner, InsertionRecord $record, DeleteInsertionRecord $action): RedirectResponse
    {
        $this->authorize('update', $learner);
        $this->ensureBelongsTo($learner, $record);

        $action->execute($record);

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'L\'entrée a été supprimée.');
    }

    private function ensureBelongsTo(Learner $learner, InsertionRecord $record): void
    {
        abort_unless($record->learner_id === $learner->id, 404);
    }
}
