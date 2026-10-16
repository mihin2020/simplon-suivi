<?php

namespace App\Enums;

/**
 * Fonts a contract can be printed in. DejaVu ships with dompdf; the others are
 * TrueType files in public/fonts/contracts/{slug}-{regular|bold|italic|bolditalic}.ttf,
 * shared by the PDF and the on-screen editor so both look the same.
 */
enum ContractFont: string
{
    case DejaVuSerif = 'dejavu-serif';
    case DejaVuSans = 'dejavu-sans';
    case Poppins = 'poppins';
    case Roboto = 'roboto';
    case OpenSans = 'open-sans';
    case Lato = 'lato';
    case Montserrat = 'montserrat';
    case Merriweather = 'merriweather';

    public const DIRECTORY = 'fonts/contracts';

    /** @var array<string, string> file suffix => [weight, style] key */
    public const FACES = [
        'regular' => 'normal normal',
        'bold' => 'bold normal',
        'italic' => 'normal italic',
        'bolditalic' => 'bold italic',
    ];

    public static function default(): self
    {
        return self::DejaVuSerif;
    }

    public function label(): string
    {
        return match ($this) {
            self::DejaVuSerif => 'DejaVu Serif (classique)',
            self::DejaVuSans => 'DejaVu Sans',
            self::Poppins => 'Poppins',
            self::Roboto => 'Roboto',
            self::OpenSans => 'Open Sans',
            self::Lato => 'Lato',
            self::Montserrat => 'Montserrat',
            self::Merriweather => 'Merriweather',
        };
    }

    /** CSS font-family name, as declared by @font-face (or built into dompdf). */
    public function family(): string
    {
        return match ($this) {
            self::DejaVuSerif => 'DejaVu Serif',
            self::DejaVuSans => 'DejaVu Sans',
            default => 'Contract '.$this->label(),
        };
    }

    public function isSerif(): bool
    {
        return in_array($this, [self::DejaVuSerif, self::Merriweather], true);
    }

    /** False for the fonts built into dompdf. */
    public function hasFiles(): bool
    {
        return ! in_array($this, [self::DejaVuSerif, self::DejaVuSans], true);
    }

    /**
     * @return list<array{value: string, label: string, family: string, files: ?string, serif: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $font) => [
            'value' => $font->value,
            'label' => $font->label(),
            'family' => $font->family(),
            // URL prefix of the four .ttf files, for the editor's @font-face.
            'files' => $font->hasFiles() ? '/'.self::DIRECTORY.'/'.$font->value : null,
            'serif' => $font->isSerif(),
        ], self::cases());
    }
}
