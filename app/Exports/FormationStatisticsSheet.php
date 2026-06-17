<?php

namespace App\Exports;

use App\Enums\Gender;
use App\Enums\InsertionStatus;
use App\Models\Formation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FormationStatisticsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    private const LAST_COLUMN = 'G';

    private int $statsStartRow = 5;
    private int $statsEndRow;
    private int $listTitleRow;
    private int $tableHeaderRow;
    private int $learnerCount;

    private array $stats;
    private Collection $learners;
    private string $projectName;
    private string $statusLabel;

    private ?string $titleOverride = null;

    public function __construct(private Formation $formation, ?array $latestInsertionMap = null)
    {
        $this->formation->loadMissing(['project:id,name', 'learners.educationLevel']);

        $latestInsertionMap ??= self::latestInsertionRecordsFor($this->formation->learners->pluck('id')->all());

        $this->projectName = $this->formation->project?->name ?? '—';
        $this->statusLabel = $this->formation->status?->label() ?? '—';
        $this->stats        = self::computeStats($this->formation, $latestInsertionMap);
        $this->learners      = self::buildLearnerRows($this->formation, $latestInsertionMap);
        $this->learnerCount  = $this->learners->count();

        $this->statsEndRow   = $this->statsStartRow + count($this->stats) - 1;
        $this->listTitleRow  = $this->statsEndRow + 2;
        $this->tableHeaderRow = $this->listTitleRow + 1;
    }

    /**
     * Calcule [learner_id => dernier statut d'insertion] pour un ensemble d'apprenants donné.
     */
    public static function latestInsertionRecordsFor(array $learnerIds): array
    {
        if (empty($learnerIds)) {
            return [];
        }

        $sub = \Illuminate\Support\Facades\DB::table('insertion_records')
            ->select('learner_id', \Illuminate\Support\Facades\DB::raw('MAX(status_changed_at) as max_date'))
            ->whereIn('learner_id', $learnerIds)
            ->groupBy('learner_id');

        $rows = \Illuminate\Support\Facades\DB::table('insertion_records as ir')
            ->select('ir.learner_id', 'ir.status')
            ->joinSub($sub, 'latest', function ($join) {
                $join->on('ir.learner_id', '=', 'latest.learner_id')
                    ->on('ir.status_changed_at', '=', 'latest.max_date');
            })
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->learner_id] = $row->status;
        }

        return $map;
    }

    public static function computeStats(Formation $formation, array $latestInsertionMap): array
    {
        $learners = $formation->learners;
        $total    = $learners->count();

        $male   = $learners->filter(fn ($l) => $l->gender === Gender::Male)->count();
        $female = $learners->filter(fn ($l) => $l->gender === Gender::Female)->count();

        $internship = 0;
        $employed   = 0;
        $searching  = 0;
        $unemployed = 0;
        foreach ($learners as $learner) {
            $status = $latestInsertionMap[$learner->id] ?? null;
            match ($status) {
                InsertionStatus::Internship->value => $internship++,
                InsertionStatus::Employed->value   => $employed++,
                InsertionStatus::Searching->value  => $searching++,
                default                             => $unemployed++,
            };
        }

        return [
            'Total apprenants' => $total,
            'Hommes'           => $male,
            'Femmes'           => $female,
            'En cours'         => $learners->filter(fn ($l) => $l->pivot->status === 'in_progress')->count(),
            'Abandonnés'       => $learners->filter(fn ($l) => $l->pivot->status === 'withdrawn')->count(),
            'Diplômés'         => $learners->filter(fn ($l) => $l->pivot->status === 'completed')->count(),
            'Transférés'       => $learners->filter(fn ($l) => $l->pivot->status === 'moved')->count(),
            'En stage'         => $internship,
            'En emploi'        => $employed,
            'En recherche'     => $searching,
            'Sans emploi'      => $unemployed,
        ];
    }

    public static function buildLearnerRows(Formation $formation, array $latestInsertionMap): Collection
    {
        $statusLabels = [
            'in_progress' => 'En cours',
            'withdrawn'   => 'Abandonné',
            'completed'   => 'Diplômé',
            'moved'       => 'Transféré',
        ];

        return $formation->learners
            ->sortBy(['last_name', 'first_name'])
            ->values()
            ->map(function ($learner) use ($latestInsertionMap, $statusLabels) {
                $insertionStatus = $latestInsertionMap[$learner->id] ?? null;
                $insertionLabel  = match ($insertionStatus) {
                    InsertionStatus::Internship->value => 'En stage',
                    InsertionStatus::Employed->value   => 'En emploi',
                    InsertionStatus::Searching->value  => 'En recherche',
                    default                             => 'Sans emploi',
                };

                return [
                    'last_name'       => $learner->last_name,
                    'first_name'      => $learner->first_name,
                    'gender_label'    => $learner->gender?->label() ?? '—',
                    'email'           => $learner->email,
                    'status_label'    => $statusLabels[$learner->pivot->status] ?? $learner->pivot->status,
                    'insertion_label' => $insertionLabel,
                ];
            });
    }

    public function setUniqueTitle(string $title): void
    {
        $this->titleOverride = $title;
    }

    public function title(): string
    {
        if ($this->titleOverride !== null) {
            return $this->titleOverride;
        }

        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->formation->name);
        $title = trim($title) ?: 'Formation';

        return mb_substr($title, 0, 31);
    }

    public function array(): array
    {
        $rows   = [];
        $rows[] = [$this->formation->name];
        $rows[] = ["Projet : {$this->projectName}   ·   Statut : {$this->statusLabel}"];
        $rows[] = [];
        $rows[] = ['RÉSUMÉ'];

        foreach ($this->stats as $label => $value) {
            $rows[] = [$label, $value];
        }

        $rows[] = [];
        $rows[] = ["LISTE DES APPRENANTS ({$this->learnerCount})"];
        $rows[] = ['#', 'Nom', 'Prénom', 'Genre', 'Email', 'Statut formation', 'Statut insertion'];

        if ($this->learnerCount === 0) {
            $rows[] = ['—', 'Aucun apprenant inscrit', '', '', '', '', ''];
        } else {
            $i = 1;
            foreach ($this->learners as $learner) {
                $rows[] = [
                    $i++,
                    $learner['last_name'],
                    $learner['first_name'],
                    $learner['gender_label'],
                    $learner['email'],
                    $learner['status_label'],
                    $learner['insertion_label'],
                ];
            }
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24,
            'B' => 24,
            'C' => 18,
            'D' => 10,
            'E' => 32,
            'F' => 18,
            'G' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $last = self::LAST_COLUMN;

        // Titre formation
        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3A4D']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);

        // Sous-titre
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '475569']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Bandeau "RÉSUMÉ"
        $sheet->mergeCells("A4:{$last}4");
        $sheet->getStyle("A4:{$last}4")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5004C']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(24);

        // Lignes de statistiques
        $sheet->getStyle("A{$this->statsStartRow}:B{$this->statsEndRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet->getStyle("A{$this->statsStartRow}:A{$this->statsEndRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F3A4D']],
        ]);
        $sheet->getStyle("B{$this->statsStartRow}:B{$this->statsEndRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'E5004C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        for ($r = $this->statsStartRow; $r <= $this->statsEndRow; $r++) {
            if (($r - $this->statsStartRow) % 2 === 1) {
                $sheet->getStyle("A{$r}:B{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }
        }

        // Bandeau "LISTE DES APPRENANTS"
        $sheet->mergeCells("A{$this->listTitleRow}:{$last}{$this->listTitleRow}");
        $sheet->getStyle("A{$this->listTitleRow}:{$last}{$this->listTitleRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3A4D']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension($this->listTitleRow)->setRowHeight(24);

        // En-tête du tableau
        $sheet->getStyle("A{$this->tableHeaderRow}:{$last}{$this->tableHeaderRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5A7B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($this->tableHeaderRow)->setRowHeight(22);

        // Corps du tableau
        $bodyRowCount = max(1, $this->learnerCount);
        $firstDataRow = $this->tableHeaderRow + 1;
        $lastDataRow  = $this->tableHeaderRow + $bodyRowCount;

        $sheet->getStyle("A{$firstDataRow}:{$last}{$lastDataRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);

        for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
            if (($r - $firstDataRow) % 2 === 1) {
                $sheet->getStyle("A{$r}:{$last}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                ]);
            }
        }

        $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->freezePane('A' . ($this->tableHeaderRow + 1));

        return [];
    }
}
