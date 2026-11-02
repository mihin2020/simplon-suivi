<?php

namespace App\Http\Requests\Insertion;

use App\Enums\InsertionStatus;
use App\Enums\WorkMode;
use App\Models\ContractType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rules shared by the recording and the update of an entry of the insertion history of a learner. */
abstract class SaveInsertionRecordRequest extends FormRequest
{
    /** Fields copied as they are on the entry; the ones not submitted are stored as null. */
    private const DETAIL_FIELDS = [
        'status_notes',
        'internship_start_date', 'internship_end_date', 'internship_company', 'internship_paid',
        'internship_contract_type', 'internship_work_mode',
        'employment_company', 'employment_start_date', 'employment_contract_type',
        'employment_work_mode', 'employment_position',
    ];

    private const CONTRACT_FILE_RULES = ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:5120'];

    /** Whether the details of an internship or of a job are mandatory for the matching status. */
    abstract protected function requiresStatusDetails(): bool;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('learner'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $internshipTypes = ContractType::internship()->pluck('name')->all();
        $employmentTypes = ContractType::employment()->pluck('name')->all();

        $ifInternship = $this->requiredWhen(InsertionStatus::Internship);
        $ifEmployed = $this->requiredWhen(InsertionStatus::Employed);

        return [
            'status' => ['required', Rule::enum(InsertionStatus::class)],
            'status_changed_at' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:1000'],
            'internship_start_date' => ['nullable', 'date', ...$ifInternship],
            'internship_end_date' => ['nullable', 'date', 'after_or_equal:internship_start_date'],
            'internship_company' => ['nullable', 'string', 'max:255', ...$ifInternship],
            'internship_paid' => ['nullable', 'boolean'],
            'internship_contract_type' => ['nullable', 'string', Rule::in($internshipTypes), ...$ifInternship],
            'internship_work_mode' => ['nullable', 'string', Rule::enum(WorkMode::class), ...$ifInternship],
            'internship_contract_file' => self::CONTRACT_FILE_RULES,
            'remove_internship_contract' => ['nullable', 'boolean'],
            'employment_company' => ['nullable', 'string', 'max:255', ...$ifEmployed],
            'employment_start_date' => ['nullable', 'date', ...$ifEmployed],
            'employment_contract_type' => ['nullable', 'string', Rule::in($employmentTypes), ...$ifEmployed],
            'employment_work_mode' => ['nullable', 'string', Rule::enum(WorkMode::class), ...$ifEmployed],
            'employment_contract_file' => self::CONTRACT_FILE_RULES,
            'remove_employment_contract' => ['nullable', 'boolean'],
            'employment_position' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The submitted status and details, with every detail that was not submitted set to null.
     *
     * @return array<string, mixed>
     */
    public function recordAttributes(): array
    {
        $validated = $this->validated();

        $attributes = ['status' => $validated['status']];

        foreach (self::DETAIL_FIELDS as $field) {
            $attributes[$field] = $validated[$field] ?? null;
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function requiredWhen(InsertionStatus $status): array
    {
        return $this->requiresStatusDetails() ? ['required_if:status,'.$status->value] : [];
    }
}
