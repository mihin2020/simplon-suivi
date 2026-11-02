<?php

namespace App\Services\Contracts;

use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\LetterheadScope;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a contract (letterhead + body) to a multi-page A4 PDF.
 *
 * Rendering takes about a second, so the result is cached on the private
 * disk and reused until the contract changes (its updated_at moves).
 */
class ContractPdfService
{
    private const CACHE_DIRECTORY = 'contracts-pdf-cache';

    /** Same grey and size as the rest of the document's discreet text. */
    private const PAGE_NUMBER_SIZE = 8.5;

    private const PAGE_NUMBER_BOTTOM_PT = 26;

    public function output(Contract $contract): string
    {
        $disk = Storage::disk('local');
        $path = self::CACHE_DIRECTORY.'/'.$contract->id.'-'.$this->fingerprint($contract).'.pdf';

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $bytes = $this->render($contract);

        // Older renderings of this contract are obsolete.
        foreach ($disk->files(self::CACHE_DIRECTORY) as $file) {
            if (str_starts_with(basename($file), $contract->id.'-')) {
                $disk->delete($file);
            }
        }
        $disk->put($path, $bytes);

        return $bytes;
    }

    public function download(Contract $contract): Response
    {
        return $this->respond($contract, 'attachment');
    }

    public function stream(Contract $contract): Response
    {
        return $this->respond($contract, 'inline');
    }

    /**
     * Render and persist the PDF on the private disk; returns the stored path.
     */
    public function store(Contract $contract): string
    {
        $path = 'contracts/'.$contract->id.'/v'.$contract->version.'.pdf';
        Storage::disk('local')->put($path, $this->output($contract));

        return $path;
    }

    private function respond(Contract $contract, string $disposition): Response
    {
        $bytes = $this->output($contract);
        $filename = 'contrat_'.str($contract->reference)->slug().'.pdf';

        // Content-Length lets the browser report real download progress.
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    private function render(Contract $contract): string
    {
        $pdf = Pdf::loadView('pdfs.contract', [
            'contract' => $contract,
            'header' => $contract->header_html,
            'footer' => $contract->footer_html,
            'body' => $contract->body_html,
            'headerHeightMm' => $contract->header_height_mm,
            'footerHeightMm' => $contract->footer_height_mm,
            'firstPageOnly' => $contract->letterhead_scope === LetterheadScope::FirstPage,
            'font' => $contract->font ?? ContractFont::default(),
            'lineSpacing' => $contract->line_spacing ?? ContractLineSpacing::default(),
        ])->setPaper('a4', 'portrait');

        $pdf->render();

        // Without a footer image, number the pages. Drawn on the canvas after
        // layout, so the total is known in a single rendering pass.
        if (! $contract->footer_height_mm) {
            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Serif');
            $width = $dompdf->getFontMetrics()->getTextWidth('Page 00 / 00', $font, self::PAGE_NUMBER_SIZE);

            $canvas->page_text(
                ($canvas->get_width() - $width) / 2,
                $canvas->get_height() - self::PAGE_NUMBER_BOTTOM_PT,
                'Page {PAGE_NUM} / {PAGE_COUNT}',
                $font,
                self::PAGE_NUMBER_SIZE,
                [0.42, 0.45, 0.5],
            );
        }

        return $pdf->output();
    }

    /**
     * Changes whenever the contract or its print template changes.
     */
    private function fingerprint(Contract $contract): string
    {
        return md5(implode('|', [
            $contract->updated_at?->format('U.u'),
            $contract->version,
            (string) @filemtime(resource_path('views/pdfs/contract.blade.php')),
        ]));
    }
}
