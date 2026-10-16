<?php

namespace App\Services\Contracts;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Extracts a .docx into simple HTML using only PHP's native ZipArchive + DOM.
 *
 * A .docx is a ZIP archive whose `word/document.xml` holds the content, so no
 * third-party library is required. The output is deliberately a small, clean
 * subset (headings, paragraphs, bold/italic/underline, tables) because the
 * result is meant to be reviewed and adjusted in the editor afterwards.
 */
class DocxExtractor
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @throws RuntimeException when the file is not a readable .docx
     */
    public function toHtml(string $absolutePath): string
    {
        $xml = $this->readDocumentXml($absolutePath);

        $dom = new DOMDocument;
        // Word XML is machine-generated; silence noisy entity warnings.
        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('Le contenu du document Word est illisible.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        $body = $xpath->query('//w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Document Word vide ou non reconnu.');
        }

        $html = [];

        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $block = match ($node->localName) {
                'p' => $this->paragraphToHtml($node, $xpath),
                'tbl' => $this->tableToHtml($node, $xpath),
                default => null,
            };

            if ($block !== null && $block !== '') {
                $html[] = $block;
            }
        }

        // Consecutive list items form one list.
        return (string) preg_replace('/(?:<li>.*?<\/li>\n?)+/su', "<ul>\n$0</ul>\n", implode("\n", $html));
    }

    /**
     * Plain text version, used as the AI input (cheaper and less noisy than HTML).
     */
    public function toText(string $absolutePath): string
    {
        return trim(html_entity_decode(
            strip_tags(str_replace(['</p>', '<br>', '</tr>'], "\n", $this->toHtml($absolutePath))),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
    }

    private function readDocumentXml(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Fichier introuvable.');
        }

        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Ce fichier n'est pas un document Word (.docx) valide.");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            throw new RuntimeException("Ce fichier n'est pas un document Word (.docx) valide.");
        }

        return $xml;
    }

    private function paragraphToHtml(DOMElement $paragraph, DOMXPath $xpath): string
    {
        $inner = $this->runsToHtml($paragraph, $xpath);

        if (trim(strip_tags($inner)) === '') {
            return '';
        }

        $tag = $this->headingTag($paragraph, $xpath);
        if ($tag !== 'p') {
            return "<{$tag}>{$inner}</{$tag}>";
        }

        // A Word list item (numbered or bulleted), or a bullet typed by hand.
        if ($xpath->query('./w:pPr/w:numPr', $paragraph)->length > 0) {
            return '<li>'.trim($inner).'</li>';
        }
        if (preg_match('/^((?:<(?:strong|em|u)>)*)\s*(?:[●•▪◦■➢➤►✓\x{F0B7}\x{F0A7}]|[-–—](?=\s))\s*/u', $inner, $m) === 1) {
            return '<li>'.$m[1].substr($inner, strlen($m[0])).'</li>';
        }

        return "<p>{$inner}</p>";
    }

    /**
     * Map a Word paragraph style onto a heading tag, covering both the English
     * ("Heading2") and French ("Titre2") style names Word produces.
     */
    private function headingTag(DOMElement $paragraph, DOMXPath $xpath): string
    {
        $styleNode = $xpath->query('.//w:pPr/w:pStyle', $paragraph)->item(0);
        if (! $styleNode instanceof DOMElement) {
            return 'p';
        }

        $style = $styleNode->getAttributeNS(self::WORD_NS, 'val');

        if (preg_match('/^(?:Heading|Titre)\s*([1-3])$/i', $style, $m) === 1) {
            return 'h'.$m[1];
        }

        if (preg_match('/^(?:Title|Titre)$/i', $style) === 1) {
            return 'h1';
        }

        return 'p';
    }

    private function runsToHtml(DOMElement $container, DOMXPath $xpath): string
    {
        $out = '';

        foreach ($xpath->query('.//w:r', $container) as $run) {
            if (! $run instanceof DOMElement) {
                continue;
            }

            $text = '';
            foreach ($run->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }
                $text .= match ($child->localName) {
                    't' => $child->textContent,
                    'tab' => ' ',
                    'br' => "\n",
                    default => '',
                };
            }

            if ($text === '') {
                continue;
            }

            $escaped = str_replace("\n", '<br>', e($text));
            $out .= $this->applyMarks($escaped, $run, $xpath);
        }

        return $out;
    }

    private function applyMarks(string $html, DOMElement $run, DOMXPath $xpath): string
    {
        foreach (['b' => 'strong', 'i' => 'em', 'u' => 'u'] as $wordMark => $tag) {
            if ($this->hasMark($run, $xpath, $wordMark)) {
                $html = "<{$tag}>{$html}</{$tag}>";
            }
        }

        return $html;
    }

    private function hasMark(DOMElement $run, DOMXPath $xpath, string $mark): bool
    {
        $node = $xpath->query(".//w:rPr/w:{$mark}", $run)->item(0);

        if (! $node instanceof DOMElement) {
            return false;
        }

        // <w:b w:val="0"/> explicitly turns the mark off.
        $val = $node->getAttributeNS(self::WORD_NS, 'val');

        return ! in_array($val, ['0', 'false'], true);
    }

    private function tableToHtml(DOMElement $table, DOMXPath $xpath): string
    {
        $rows = '';

        foreach ($xpath->query('.//w:tr', $table) as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = '';
            foreach ($xpath->query('.//w:tc', $row) as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }
                $cells .= '<td>'.$this->runsToHtml($cell, $xpath).'</td>';
            }

            if ($cells !== '') {
                $rows .= "<tr>{$cells}</tr>";
            }
        }

        return $rows === '' ? '' : "<table>{$rows}</table>";
    }
}
