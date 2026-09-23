<?php

namespace App\Exports;

use App\Models\AgeRange;
use App\Models\EducationLevel;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;
use App\Support\LearnerFormAttributes;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export compatible with /learners/import column headers.
 */
class FormResponsesLearnerExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /** @var list<string> */
    private array $columns;

    /** @var array<string, string> education level id => name */
    private array $educationLevels;

    /** @var array<string, string> age range id => name */
    private array $ageRanges;

    public function __construct(
        private readonly Form $form,
        private readonly ?string $statusFilter = null,
    ) {
        $this->form->load(['fields', 'responses.answers.field']);
        $this->columns = LearnerFormAttributes::importColumns();
        $this->educationLevels = EducationLevel::query()->pluck('name', 'id')->all();
        $this->ageRanges = AgeRange::query()->pluck('name', 'id')->all();
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function array(): array
    {
        $attrToColumn = [];
        foreach (LearnerFormAttributes::all() as $attr) {
            if ($attr['import_column']) {
                $attrToColumn[$attr['key']] = $attr['import_column'];
            }
        }

        $query = $this->form->responses()->with(['answers.field'])->latest('submitted_at');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $rows = [];

        foreach ($query->get() as $response) {
            /** @var FormResponse $response */
            $row = array_fill_keys($this->columns, '');

            foreach ($response->answers as $answer) {
                $attr = $answer->field?->learner_attribute;
                if (! $attr || ! isset($attrToColumn[$attr])) {
                    continue;
                }

                $column = $attrToColumn[$attr];
                $row[$column] = $this->formatAnswer($attr, $answer);
            }

            if ($row['email'] === '' && $response->email) {
                $row['email'] = $response->email;
            }

            if ($row['prenom'] === '' && $row['nom'] === '') {
                continue;
            }

            $rows[] = array_values($row);
        }

        return $rows;
    }

    private function formatAnswer(string $attr, FormAnswer $answer): string
    {
        if ($attr === 'photo_path' && filled($answer->file_path)) {
            return URL::temporarySignedRoute(
                'forms.responses.answers.signed-download',
                now()->addDays(14),
                [
                    'form' => $this->form->id,
                    'response' => $answer->form_response_id,
                    'answer' => $answer->id,
                ]
            );
        }

        if (filled($answer->file_original_name)) {
            if (filled($answer->file_path)) {
                return URL::temporarySignedRoute(
                    'forms.responses.answers.signed-download',
                    now()->addDays(14),
                    [
                        'form' => $this->form->id,
                        'response' => $answer->form_response_id,
                        'answer' => $answer->id,
                    ]
                );
            }

            return (string) $answer->file_original_name;
        }

        $value = $answer->value_json ?? $answer->value_text;
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $value = trim((string) ($value ?? ''));

        if ($attr === 'gender') {
            return match (strtolower($value)) {
                'male', 'm', 'homme', 'masculin' => 'M',
                'female', 'f', 'femme', 'feminin', 'féminin' => 'F',
                default => $value,
            };
        }

        if ($attr === 'education_level_id') {
            return $this->educationLevels[$value] ?? $value;
        }

        if ($attr === 'age_range_id') {
            return $this->ageRanges[$value] ?? $value;
        }

        if ($attr === 'birth_date' && $value !== '') {
            try {
                return \Carbon\Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
