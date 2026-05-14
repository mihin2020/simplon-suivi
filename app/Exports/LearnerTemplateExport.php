<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LearnerTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'prenom',
            'nom',
            'email',
            'telephone',
            'genre',
            'date_naissance',
            'lieu_naissance',
            'niveau_etudes',
            'talent',
            'contact_urgence_nom',
            'contact_urgence_prenom',
            'contact_urgence_telephone',
            'adresse',
            'localisation',
            'profil',
            'organisation',
            'tranche_age',
            'domaine_etudes',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Aminata',
                'Ouedraogo',
                'aminata.ouedraogo@email.com',
                '+226 70 00 00 01',
                'F',
                '2001-03-15',
                'Ouagadougou',
                'Bac+2',
                'Développement web',
                'Ouedraogo',
                'Mamadou',
                '+226 70 00 00 02',
                'Rue 123, Quartier XYZ',
                'Ouagadougou, Burkina Faso',
                'Développeur web junior',
                'Simplon',
                '',
                'Informatique',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // prenom
            'B' => 18, // nom
            'C' => 30, // email
            'D' => 18, // telephone
            'E' => 10, // genre
            'F' => 16, // date_naissance
            'G' => 20, // lieu_naissance
            'H' => 18, // niveau_etudes
            'I' => 25, // talent
            'J' => 22, // contact_urgence_nom
            'K' => 24, // contact_urgence_prenom
            'L' => 26, // contact_urgence_telephone
            'M' => 30, // adresse
            'N' => 25, // localisation
            'O' => 25, // profil
            'P' => 22, // organisation
            'Q' => 18, // tranche_age
            'R' => 20, // domaine_etudes
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Style de l'en-tête (ligne 1)
        $sheet->getStyle('A1:R1')->applyFromArray([
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
                'wrapText'   => true,
            ],
        ]);

        // Hauteur de la ligne d'en-tête
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Colonnes obligatoires A et B en rouge clair
        $sheet->getStyle('A1:B1')->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'C0003E'],
            ],
        ]);

        // Style de la ligne d'exemple (ligne 2)
        $sheet->getStyle('A2:R2')->applyFromArray([
            'font' => ['color' => ['rgb' => '515f74'], 'italic' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F9FAFB'],
            ],
        ]);

        // Bordures sur toute la zone
        $sheet->getStyle('A1:R2')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E0E3E5'],
                ],
            ],
        ]);

        return [];
    }
}
