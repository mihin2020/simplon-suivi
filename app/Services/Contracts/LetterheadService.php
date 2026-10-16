<?php

namespace App\Services\Contracts;

use App\Enums\LetterheadScope;
use App\Models\Letterhead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores letterhead images and turns them into the HTML dompdf prints.
 */
class LetterheadService
{
    private const DIRECTORY = 'document-layouts';

    /**
     * Create or update a letterhead. An omitted image keeps the current one.
     */
    public function save(string $name, ?UploadedFile $header, ?UploadedFile $footer, ?Letterhead $letterhead = null): Letterhead
    {
        $letterhead ??= new Letterhead;
        $letterhead->name = $name;

        if ($header !== null) {
            $this->replaceImage($letterhead, 'header', $header, Letterhead::MAX_HEADER_HEIGHT_MM);
        }

        if ($footer !== null) {
            $this->replaceImage($letterhead, 'footer', $footer, Letterhead::MAX_FOOTER_HEIGHT_MM);
        }

        $letterhead->save();

        return $letterhead;
    }

    /**
     * Letterheads as offered in the contract forms, with their images.
     *
     * @return list<array{id: string, name: string, header_url: ?string, footer_url: ?string, header_height_mm: ?float, footer_height_mm: ?float}>
     */
    public function options(): array
    {
        return Letterhead::orderBy('name')->get()->map(fn (Letterhead $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'header_url' => $this->url($l->header_image_path),
            'footer_url' => $this->url($l->footer_image_path),
            'header_height_mm' => $l->header_height_mm,
            'footer_height_mm' => $l->footer_height_mm,
        ])->values()->all();
    }

    /**
     * The letterhead columns of a contract. The bands are frozen as HTML, so a
     * later change of the letterhead leaves contracts already printed alone;
     * no letterhead clears them.
     *
     * @return array<string, mixed>
     */
    public function contractFields(?Letterhead $letterhead, LetterheadScope $scope): array
    {
        if ($letterhead === null) {
            return [
                'letterhead_id' => null,
                'header_html' => null,
                'footer_html' => null,
                'header_height_mm' => null,
                'footer_height_mm' => null,
                'letterhead_scope' => null,
            ];
        }

        return [
            'letterhead_id' => $letterhead->id,
            'header_html' => $this->headerHtml($letterhead) ?? '',
            'footer_html' => $this->footerHtml($letterhead) ?? '',
            'header_height_mm' => $letterhead->header_height_mm ?? 0,
            'footer_height_mm' => $letterhead->footer_height_mm ?? 0,
            'letterhead_scope' => $scope,
        ];
    }

    public function headerHtml(Letterhead $letterhead): ?string
    {
        return $this->bandHtml($letterhead->header_image_path, $letterhead->header_height_mm);
    }

    public function footerHtml(Letterhead $letterhead): ?string
    {
        return $this->bandHtml($letterhead->footer_image_path, $letterhead->footer_height_mm);
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function replaceImage(Letterhead $letterhead, string $side, UploadedFile $file, int $maxHeightMm): void
    {
        [$width, $height] = getimagesize($file->getRealPath()) ?: [1, 1];

        // Fitted to the A4 width; an image too tall is capped (and then narrower than the page).
        $heightMm = min(Letterhead::PAGE_WIDTH_MM * $height / max($width, 1), $maxHeightMm);

        $previous = $letterhead->{$side.'_image_path'};

        $letterhead->{$side.'_image_path'} = $file->storeAs(
            self::DIRECTORY,
            Str::uuid().'.'.$file->guessExtension(),
            'public',
        );
        $letterhead->{$side.'_height_mm'} = round($heightMm, 1);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }

    /**
     * A full-width band holding the image at its stored height. dompdf only
     * loads local files, hence the absolute path.
     */
    private function bandHtml(?string $path, ?float $heightMm): ?string
    {
        if (! $path || ! $heightMm) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);

        if (! is_file($absolute)) {
            return null;
        }

        return sprintf(
            '<div style="width:%dmm;height:%smm;text-align:center;"><img src="%s" style="height:%smm;" alt=""></div>',
            Letterhead::PAGE_WIDTH_MM,
            $heightMm,
            e($absolute),
            $heightMm,
        );
    }
}
