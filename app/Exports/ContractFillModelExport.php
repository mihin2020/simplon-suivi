<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel model of one contract: one column per person-specific passage, the
 * first row holding the values of the contract it comes from as an example.
 */
class ContractFillModelExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  list<array{key: string, label: string, value: string}>  $fields
     */
    public function __construct(
        private readonly array $fields,
        private readonly ?string $exampleEmail = null,
    ) {}

    /** Extra last column: where each generated contract will be sent. */
    public const EMAIL_HEADING = 'Email du destinataire';

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [...array_column($this->fields, 'label'), self::EMAIL_HEADING];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [[...array_column($this->fields, 'value'), (string) $this->exampleEmail]];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        // The example row is greyed out: it is the source contract, to replace or delete.
        $sheet->getComment('A2')->getText()->createTextRun(
            'Ligne d\'exemple (contrat d\'origine). Remplacez-la ou supprimez-la, puis ajoutez une ligne par personne.'
        );

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5004C']]],
            2 => ['font' => ['italic' => true, 'color' => ['rgb' => '868E96']]],
        ];
    }
}
