<?php

namespace App\Exports;

use App\Models\Form;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FormResponsesExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(private readonly Form $form)
    {
        $this->form->load(['fields', 'responses.answers']);
    }

    public function headings(): array
    {
        $heads = ['ID', 'E-mail', 'Statut', 'Soumis le'];
        foreach ($this->form->fields as $field) {
            if ($field->type->isAnswerable()) {
                $heads[] = $field->label;
            }
        }

        return $heads;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->form->responses()->with('answers')->latest('submitted_at')->get() as $response) {
            $answersByField = $response->answers->keyBy('form_field_id');
            $row = [
                $response->id,
                $response->email,
                $response->status->label(),
                optional($response->submitted_at)->format('Y-m-d H:i'),
            ];

            foreach ($this->form->fields as $field) {
                if (! $field->type->isAnswerable()) {
                    continue;
                }
                $answer = $answersByField->get($field->id);
                if (! $answer) {
                    $row[] = '';
                } elseif (filled($answer->file_path)) {
                    $row[] = URL::temporarySignedRoute(
                        'forms.responses.answers.signed-download',
                        now()->addDays(14),
                        [
                            'form' => $this->form->id,
                            'response' => $response->id,
                            'answer' => $answer->id,
                        ]
                    );
                } elseif (is_array($answer->value_json)) {
                    $row[] = implode(', ', $answer->value_json);
                } else {
                    $row[] = $answer->value_text;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
