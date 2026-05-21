<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CohortLearnerTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'nom',
            'prenom',
            'genre',
            'date_naissance',
            'email',
            'telephone',
            'niveau_etudes',
            'contact_urgence_nom',
            'contact_urgence_prenom',
            'contact_urgence_telephone',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Ouedraogo',
                'Aminata',
                'F',
                '2001-03-15',
                'aminata.ouedraogo@email.com',
                '+226 70 00 00 01',
                'Bac+2',
                'Ouedraogo',
                'Mamadou',
                '+226 70 00 00 02',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 20,
            'C' => 10,
            'D' => 18,
            'E' => 32,
            'F' => 20,
            'G' => 20,
            'H' => 24,
            'I' => 24,
            'J' => 26,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = 'J';
        $range   = "A1:{$lastCol}1";

        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F3A4D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Colonnes obligatoires (nom, prenom) en rouge Simplon
        $sheet->getStyle('A1:B1')->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'C0003E'],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(26);

        // Ligne d'exemple en italique grisé
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['color' => ['rgb' => '515f74'], 'italic' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F9FAFB'],
            ],
        ]);

        // Bordures
        $sheet->getStyle("A1:{$lastCol}2")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E0E3E5'],
                ],
            ],
        ]);

        // Note genre en dessous
        $sheet->setCellValue('A4', 'Genre : M = Homme, F = Femme');
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9aaabb'], 'size' => 9],
        ]);

        // Note date en dessous
        $sheet->setCellValue('A5', 'Date de naissance : format AAAA-MM-JJ (ex: 2001-03-15)');
        $sheet->getStyle('A5')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9aaabb'], 'size' => 9],
        ]);

        return [];
    }
}
