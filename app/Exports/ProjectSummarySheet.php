<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectSummarySheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    private const LAST_COLUMN = 'H';
    private const TABLE_HEADER_ROW = 4;

    private Collection $rows;

    public function __construct(private Project $project, array $latestInsertionMap)
    {
        $this->project->loadMissing('formations');

        $this->rows = $this->project->formations->map(function ($formation) use ($latestInsertionMap) {
            $stats = FormationStatisticsSheet::computeStats($formation, $latestInsertionMap);

            return [
                'name'       => $formation->name,
                'status'     => $formation->status?->label() ?? '—',
                'total'      => $stats['Total apprenants'],
                'male'       => $stats['Hommes'],
                'female'     => $stats['Femmes'],
                'in_progress'=> $stats['En cours'],
                'completed'  => $stats['Diplômés'],
                'employed'   => $stats['En emploi'],
            ];
        });
    }

    public function title(): string
    {
        return 'Résumé projet';
    }

    public function array(): array
    {
        $rows   = [];
        $rows[] = [$this->project->name];
        $rows[] = ['Récapitulatif des formations'];
        $rows[] = [];
        $rows[] = ['Formation', 'Statut', 'Total', 'Hommes', 'Femmes', 'En cours', 'Diplômés', 'En emploi'];

        foreach ($this->rows as $row) {
            $rows[] = [
                $row['name'], $row['status'], $row['total'], $row['male'],
                $row['female'], $row['in_progress'], $row['completed'], $row['employed'],
            ];
        }

        $rows[] = [
            'TOTAL', '',
            $this->rows->sum('total'),
            $this->rows->sum('male'),
            $this->rows->sum('female'),
            $this->rows->sum('in_progress'),
            $this->rows->sum('completed'),
            $this->rows->sum('employed'),
        ];

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 16,
            'C' => 10,
            'D' => 10,
            'E' => 10,
            'F' => 12,
            'G' => 12,
            'H' => 12,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $last         = self::LAST_COLUMN;
        $headerRow    = self::TABLE_HEADER_ROW;
        $firstDataRow = $headerRow + 1;
        $count        = $this->rows->count();
        $lastDataRow  = $firstDataRow + $count - 1;
        $totalRow     = $lastDataRow + 1;

        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3A4D']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);

        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '475569']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5004C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        if ($count > 0) {
            $sheet->getStyle("A{$firstDataRow}:{$last}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);
            $sheet->getStyle("C{$firstDataRow}:{$last}{$lastDataRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                if (($r - $firstDataRow) % 2 === 1) {
                    $sheet->getStyle("A{$r}:{$last}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                    ]);
                }
            }
        }

        $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '1F3A4D']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1F3A4D']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A{$totalRow}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]);

        $sheet->freezePane('A' . $firstDataRow);

        return [];
    }
}
