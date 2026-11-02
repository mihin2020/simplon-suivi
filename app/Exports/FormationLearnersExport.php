<?php

namespace App\Exports;

use App\Enums\LearnerStatus;
use App\Models\Formation;
use App\Models\Learner;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Every learner enrolled in a formation, with their personal information (no files, no photos).
 */
class FormationLearnersExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles
{
    private const MARITAL_STATUSES = [
        'single' => 'Célibataire',
        'married' => 'Marié(e)',
        'divorced' => 'Divorcé(e)',
        'widowed' => 'Veuf / Veuve',
    ];

    public function __construct(private readonly Formation $formation) {}

    public function headings(): array
    {
        return [
            'N°', 'Nom', 'Prénom', 'Genre', 'Date de naissance', 'Lieu de naissance', 'Email', 'Téléphone',
            'Adresse', 'Localité', 'Niveau d\'études', 'Dernier diplôme', 'Domaine d\'études', 'Tranche d\'âge',
            'Situation matrimoniale', 'Nombre d\'enfants', 'Vulnérabilité', 'Organisation', 'Profil', 'Talent',
            'N° CNIB', 'Contact d\'urgence (nom)', 'Contact d\'urgence (prénom)', 'Contact d\'urgence (téléphone)',
            'Statut dans la formation', 'Date d\'inscription',
        ];
    }

    public function array(): array
    {
        $learners = $this->formation->learners()
            ->with(['educationLevel', 'lastDiploma', 'ageRange', 'vulnerability'])
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get();

        return $learners->values()->map(fn (Learner $learner, int $index) => [
            $index + 1,
            $learner->last_name,
            $learner->first_name,
            $learner->gender?->label(),
            $learner->birth_date?->format('d/m/Y'),
            $learner->birth_place,
            $learner->email,
            $learner->phone,
            $learner->address,
            $learner->location,
            $learner->educationLevel?->name,
            $learner->lastDiploma?->name,
            $learner->study_field,
            $learner->ageRange?->name,
            self::MARITAL_STATUSES[$learner->marital_status] ?? null,
            $learner->children_count,
            $learner->vulnerability?->name,
            $learner->organization,
            $learner->profile,
            $learner->talent,
            $learner->cnib_number,
            $learner->emergency_contact_name,
            $learner->emergency_contact_firstname,
            $learner->emergency_contact_phone,
            LearnerStatus::tryFrom($learner->pivot->status)?->label(),
            $learner->pivot->enrolled_at ? Carbon::parse($learner->pivot->enrolled_at)->format('d/m/Y') : null,
        ])->all();
    }

    /**
     * Keep every text as a string: the default binder would turn "=..." into a live formula
     * (executing attacker-controlled content) and "+226..." phone numbers into numbers.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:Z1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3A4D']],
        ]);

        return [];
    }
}
