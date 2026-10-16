<?php

namespace App\Services\Contracts;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extracts the text layer of a .pdf. Scanned PDFs (images only) have no text
 * layer and are reported as unreadable rather than silently imported empty.
 */
class PdfExtractor
{
    /**
     * @throws RuntimeException when the file is not a readable text PDF
     */
    public function toText(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Fichier introuvable.');
        }

        try {
            $text = (new Parser)->parseFile($absolutePath)->getText();
        } catch (Throwable) {
            throw new RuntimeException("Ce fichier n'est pas un PDF lisible.");
        }

        if (trim($text) === '') {
            throw new RuntimeException(
                'Ce PDF ne contient pas de texte exploitable (document scanné ?). Utilisez un PDF texte ou un fichier Word.'
            );
        }

        return $text;
    }
}
