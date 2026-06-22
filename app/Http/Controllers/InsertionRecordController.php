<?php

namespace App\Http\Controllers;

use App\Enums\InsertionStatus;
use App\Enums\WorkMode;
use App\Models\ContractType;
use App\Models\InsertionRecord;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $validated = $request->validate($this->rules());

        $record = new InsertionRecord([
            'learner_id' => $learner->id,
            'status' => $validated['status'],
            'status_changed_at' => $validated['status_changed_at'] ?? now(),
            'status_notes' => $validated['status_notes'] ?? null,
            'internship_start_date' => $validated['internship_start_date'] ?? null,
            'internship_end_date' => $validated['internship_end_date'] ?? null,
            'internship_company' => $validated['internship_company'] ?? null,
            'internship_paid' => $validated['internship_paid'] ?? null,
            'internship_contract_type' => $validated['internship_contract_type'] ?? null,
            'internship_work_mode' => $validated['internship_work_mode'] ?? null,
            'employment_company' => $validated['employment_company'] ?? null,
            'employment_start_date' => $validated['employment_start_date'] ?? null,
            'employment_contract_type' => $validated['employment_contract_type'] ?? null,
            'employment_work_mode' => $validated['employment_work_mode'] ?? null,
            'employment_position' => $validated['employment_position'] ?? null,
            'recorded_by' => Auth::id(),
        ]);

        $this->storeInternshipContract($request, $record);
        $this->storeEmploymentContract($request, $record);

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

        $validated = $request->validate($this->rules(forUpdate: true));

        $record->fill([
            'status' => $validated['status'],
            'status_notes' => $validated['status_notes'] ?? null,
            'internship_start_date' => $validated['internship_start_date'] ?? null,
            'internship_end_date' => $validated['internship_end_date'] ?? null,
            'internship_company' => $validated['internship_company'] ?? null,
            'internship_paid' => $validated['internship_paid'] ?? null,
            'internship_contract_type' => $validated['internship_contract_type'] ?? null,
            'internship_work_mode' => $validated['internship_work_mode'] ?? null,
            'employment_company' => $validated['employment_company'] ?? null,
            'employment_start_date' => $validated['employment_start_date'] ?? null,
            'employment_contract_type' => $validated['employment_contract_type'] ?? null,
            'employment_work_mode' => $validated['employment_work_mode'] ?? null,
            'employment_position' => $validated['employment_position'] ?? null,
        ]);

        if ($request->boolean('remove_internship_contract')) {
            $this->deleteInternshipContract($record);
        }

        if ($request->boolean('remove_employment_contract')) {
            $this->deleteEmploymentContract($record);
        }

        $this->storeInternshipContract($request, $record);
        $this->storeEmploymentContract($request, $record);

        $record->save();

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

        $this->deleteInternshipContract($record);
        $this->deleteEmploymentContract($record);
        $record->delete();

        return redirect()
            ->route('learners.show', $learner)
            ->with('success', 'L\'entrée a été supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $forUpdate = false): array
    {
        $internshipTypes = ContractType::internship()->pluck('name')->all();
        $employmentTypes = ContractType::employment()->pluck('name')->all();

        $requiredIfInternship = $forUpdate ? [] : ['required_if:status,internship'];
        $requiredIfEmployed = $forUpdate ? [] : ['required_if:status,employed'];

        return [
            'status' => ['required', 'string', 'in:searching,internship,employed,unemployed'],
            'status_changed_at' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:1000'],
            'internship_start_date' => array_merge(['nullable', 'date'], $requiredIfInternship),
            'internship_end_date' => ['nullable', 'date', 'after_or_equal:internship_start_date'],
            'internship_company' => array_merge(['nullable', 'string', 'max:255'], $requiredIfInternship),
            'internship_paid' => ['nullable', 'boolean'],
            'internship_contract_type' => array_merge(
                ['nullable', 'string', Rule::in($internshipTypes)],
                $requiredIfInternship,
            ),
            'internship_work_mode' => array_merge(
                ['nullable', 'string', Rule::enum(WorkMode::class)],
                $requiredIfInternship,
            ),
            'internship_contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:5120'],
            'remove_internship_contract' => ['nullable', 'boolean'],
            'employment_company' => array_merge(['nullable', 'string', 'max:255'], $requiredIfEmployed),
            'employment_start_date' => array_merge(['nullable', 'date'], $requiredIfEmployed),
            'employment_contract_type' => array_merge(
                ['nullable', 'string', Rule::in($employmentTypes)],
                $requiredIfEmployed,
            ),
            'employment_work_mode' => array_merge(
                ['nullable', 'string', Rule::enum(WorkMode::class)],
                $requiredIfEmployed,
            ),
            'employment_contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:5120'],
            'remove_employment_contract' => ['nullable', 'boolean'],
            'employment_position' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function storeInternshipContract(Request $request, InsertionRecord $record): void
    {
        if (! $request->hasFile('internship_contract_file')) {
            return;
        }

        $this->deleteInternshipContract($record);
        $this->assignContractFile(
            $request->file('internship_contract_file'),
            $record,
            'internship_contract_path',
            'internship_contract_original_name',
            'insertion-records/internship',
        );
    }

    private function storeEmploymentContract(Request $request, InsertionRecord $record): void
    {
        if (! $request->hasFile('employment_contract_file')) {
            return;
        }

        $this->deleteEmploymentContract($record);
        $this->assignContractFile(
            $request->file('employment_contract_file'),
            $record,
            'employment_contract_path',
            'employment_contract_original_name',
            'insertion-records/employment',
        );
    }

    private function assignContractFile(
        UploadedFile $file,
        InsertionRecord $record,
        string $pathColumn,
        string $nameColumn,
        string $directory,
    ): void {
        $record->{$pathColumn} = $file->store($directory, 'public');
        $record->{$nameColumn} = $file->getClientOriginalName();
    }

    private function deleteInternshipContract(InsertionRecord $record): void
    {
        if ($record->internship_contract_path) {
            Storage::disk('public')->delete($record->internship_contract_path);
            $record->internship_contract_path = null;
            $record->internship_contract_original_name = null;
        }
    }

    private function deleteEmploymentContract(InsertionRecord $record): void
    {
        if ($record->employment_contract_path) {
            Storage::disk('public')->delete($record->employment_contract_path);
            $record->employment_contract_path = null;
            $record->employment_contract_original_name = null;
        }
    }
}
