<?php

namespace App\Services\Contracts;

/**
 * Rebuilds the structure of a contract extracted as plain text (PDF, .txt).
 *
 * A PDF text layer has one line per *visual* line: paragraphs are cut every
 * 80 characters, bullets lose their list, article titles look like any other
 * line. This class puts the document back together - paragraphs, article
 * headings, sub-articles, bullet lists - and takes the document title and the
 * signature block apart, since the PDF prints both on its own.
 */
class ContractTextStructurer
{
    /** Characters a PDF uses as bullets, followed by an optional invisible joiner. */
    private const BULLET = '/^(?:[●•▪◦○■□➢➤►✓✔\x{F0B7}\x{F0A7}\x{F076}]|[-–—*](?=\s|\x{200B})|o(?=\s))[\s\x{200B}]*/u';

    private const ARTICLE = '/^(?:article|art\.)\s*(\d+|[IVXLC]+\b|premier|1er)\s*(?:er\b)?\s*[:.\-–—]?\s*(.*)$/iu';

    private const SUB_ARTICLE = '/^(\d+\.\d+(?:\.\d+)?\.?)\s+(.+)$/u';

    /** "Page 2 sur 4", "2/4", "- 2 -": running page numbers repeated on every page. */
    private const PAGE_NUMBER = '/^(?:page\s*)?[-–]?\s*\d+\s*(?:(?:\/|sur|of)\s*\d+)?\s*[-–]?$/iu';

    /** A line ending like this closes its paragraph even if it is long. */
    private const SENTENCE_END = '/[.:;!?»)]$/u';

    /**
     * @return array{title: ?string, body_html: string, signatories: ?array{left_title: string, left_name: string, right_title: string, right_name: string}}
     */
    public function structure(string $text): array
    {
        $lines = $this->lines($text);
        [$lines, $signatories] = $this->takeSignatures($lines);
        [$title, $lines] = $this->takeTitle($lines);

        return [
            'title' => $title,
            'body_html' => $this->render($this->blocks($lines)),
            'signatories' => $signatories,
        ];
    }

    /**
     * Normalised lines; an empty string marks a blank line (a hard break).
     *
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $text = str_replace(["\u{00AD}", "\u{FEFF}", "\t"], ['', '', ' '], $text);
        // Word exports bullets as a symbol plus an invisible joiner ("-\u{200B}Tâche"):
        // the joiner marks a real bullet, even glued mid-line ("… Chèque. ●5.3. Condition").
        $text = (string) preg_replace('/(?<=\S)[ \x{00A0}]+([●•▪➢➤►])\x{200B}?/u', "\n$1 ", $text);
        $text = (string) preg_replace('/(?<=\S)[ \x{00A0}]+([-–])\x{200B}/u', "\n$1 ", $text);
        $text = (string) preg_replace('/^([●•▪◦○■□➢➤►\-–—*])\x{200B}/mu', '$1 ', $text);
        // A superscript ordinal falls on its own line: "du 1" / "er" / "juin 2023".
        $text = (string) preg_replace('/(\d)[ \x{00A0}]*\R[ \x{00A0}]*(er|ère|re|e|ème)[ \x{00A0}]*\R[ \x{00A0}]*/u', '$1$2 ', $text);

        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            // Wide gaps are kept for now: they mark two-column lines (signatures).
            $clean = trim(str_replace(["\u{200B}", "\u{00A0}"], ['', ' '], $line));

            if ($clean !== '' && preg_match(self::PAGE_NUMBER, $clean) === 1) {
                continue;
            }

            $lines[] = $clean;
        }

        return $lines;
    }

    /**
     * The signature block is the last lines split in two columns by a wide
     * gap: titles first ("Pour SIMPLON …   Pour le Prestataire"), then names.
     *
     * @param  list<string>  $lines
     * @return array{0: list<string>, 1: ?array{left_title: string, left_name: string, right_title: string, right_name: string}}
     */
    private function takeSignatures(array $lines): array
    {
        $columns = [];
        $firstIndex = null;

        for ($i = count($lines) - 1; $i >= 0 && $i >= count($lines) - 15; $i--) {
            if ($lines[$i] === '') {
                continue;
            }
            $parts = preg_split('/\s{5,}/u', $lines[$i]) ?: [];
            if (count($parts) !== 2) {
                break;
            }
            array_unshift($columns, array_map(fn (string $p) => trim($p), $parts));
            $firstIndex = $i;
        }

        // One line is enough ("L'employeur      Le travailleur"): names stay empty.
        if ($firstIndex === null) {
            return [array_map(fn (string $l) => $this->squash($l), $lines), null];
        }

        $kept = array_map(fn (string $l) => $this->squash($l), array_slice($lines, 0, $firstIndex));

        return [$kept, [
            'left_title' => $columns[0][0],
            'right_title' => $columns[0][1],
            'left_name' => implode(' ', array_column(array_slice($columns, 1), 0)),
            'right_name' => implode(' ', array_column(array_slice($columns, 1), 1)),
        ]];
    }

    /**
     * The title is the opening lines before the parties are introduced.
     * Short, upper-case or not, it never ends with a full stop.
     *
     * @param  list<string>  $lines
     * @return array{0: ?string, 1: list<string>}
     */
    private function takeTitle(array $lines): array
    {
        $titleLines = [];
        $index = 0;

        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }
            // The title opens the document in capitals; a first line in
            // ordinary case means the document has no title to take.
            if ($titleLines === [] && ! $this->isMostlyUpperCase($line)) {
                break;
            }
            if (preg_match('/^entre\b|^(?:article|art\.)\s*\d/iu', $line) === 1
                || mb_strlen($line) > 90 || preg_match('/[.;]$/u', $line) === 1 || count($titleLines) >= 3) {
                break;
            }
            $titleLines[] = $line;
        }

        if ($titleLines === []) {
            return [null, $lines];
        }

        $title = implode(' : ', $titleLines);

        return [$title, array_slice($lines, $index)];
    }

    /**
     * Group lines into typed blocks, joining the lines a paragraph was cut into.
     *
     * @param  list<string>  $lines
     * @return list<array{type: string, text: string}>
     */
    private function blocks(array $lines): array
    {
        $blocks = [];
        $current = null;
        $wrapWidth = $this->wrapWidth($lines);

        $flush = function () use (&$blocks, &$current) {
            if ($current !== null && trim($current['text']) !== '') {
                $blocks[] = $current;
            }
            $current = null;
        };

        foreach ($lines as $line) {
            if ($line === '') {
                $flush();

                continue;
            }

            // The previous line filled the width without ending a sentence:
            // this one carries on the same paragraph, whatever it looks like
            // (an upper-case "FASO." must not become a heading).
            $continues = $current !== null && in_array($current['type'], ['p', 'li'], true)
                && mb_strlen($current['last']) >= $wrapWidth * 0.75
                && preg_match(self::SENTENCE_END, $current['last']) !== 1;

            $starts = $this->startsBlock($line, $continues);
            if ($starts !== null) {
                $flush();
                $current = $starts;
            } elseif ($current === null || in_array($current['type'], ['h2', 'h3', 'strong'], true)
                // A short line followed by a capital: the line was not wrapped, it ended.
                || (! $continues && preg_match('/^\p{Lu}/u', $line) === 1)) {
                $flush();
                $current = ['type' => 'p', 'text' => $line, 'last' => $line];
            } else {
                $current['text'] .= ' '.$line;
                $current['last'] = $line;
            }

            // A line well short of the page width, or one ending a sentence
            // before a capital, is the end of its paragraph.
            $last = $current['last'] ?? $line;
            if (in_array($current['type'], ['h2', 'h3', 'strong'], true)
                || (mb_strlen($last) < $wrapWidth * 0.7 && preg_match(self::SENTENCE_END, $last) === 1)
                || mb_strlen($last) < $wrapWidth * 0.45) {
                $flush();
            }
        }
        $flush();

        return $blocks;
    }

    /**
     * @return array{type: string, text: string, last: string}|null
     */
    private function startsBlock(string $line, bool $continues): ?array
    {
        if (preg_match(self::BULLET, $line, $m) === 1 && mb_strlen($line) > mb_strlen($m[0])) {
            $text = mb_substr($line, mb_strlen($m[0]));

            return ['type' => 'li', 'text' => $text, 'last' => $line];
        }

        if (preg_match(self::ARTICLE, $line, $m) === 1 && mb_strlen($line) <= 120) {
            $number = in_array(mb_strtolower($m[1]), ['premier', '1er'], true) ? '1' : mb_strtoupper($m[1]);
            $title = rtrim(trim($m[2]), ' :.');

            return ['type' => 'h2', 'text' => 'Article '.$number.($title !== '' ? ' : '.$title : ''), 'last' => $line];
        }

        if ($continues) {
            return null;
        }

        if (preg_match(self::SUB_ARTICLE, $line, $m) === 1) {
            // A short numbered line is a sub-heading; a long one is a numbered paragraph.
            return mb_strlen($line) <= 80 && preg_match('/[.;]$/u', $line) !== 1
                ? ['type' => 'h3', 'text' => rtrim($m[1], '.').'. '.rtrim(trim($m[2]), ' :'), 'last' => $line]
                : ['type' => 'p', 'text' => $line, 'last' => $line];
        }

        // "ENTRE LES SOUSSIGNÉS :", "D'autre part": short upper-case lines stand alone, in bold.
        if (mb_strlen($line) <= 70 && preg_match('/\p{Ll}/u', $line) !== 1 && preg_match('/\p{Lu}{3}/u', $line) === 1) {
            return ['type' => 'strong', 'text' => $line, 'last' => $line];
        }
        if (preg_match('/^(?:d[\'’]une part|d[\'’]autre part|et\s*:?)\s*[,;:]?(?:\s*et)?$/iu', $line) === 1) {
            return ['type' => 'strong', 'text' => $line, 'last' => $line];
        }

        return null;
    }

    /**
     * Width at which the PDF wrapped its lines: the length most long lines reach.
     *
     * @param  list<string>  $lines
     */
    private function wrapWidth(array $lines): int
    {
        $lengths = array_map('mb_strlen', array_filter($lines, fn (string $l) => $l !== ''));
        if ($lengths === []) {
            return 80;
        }
        sort($lengths);

        return max(40, $lengths[(int) floor((count($lengths) - 1) * 0.8)]);
    }

    /**
     * @param  list<array{type: string, text: string}>  $blocks
     */
    private function render(array $blocks): string
    {
        $html = '';
        $inList = false;

        foreach ($blocks as $block) {
            if ($block['type'] === 'li' && ! $inList) {
                $html .= '<ul>';
                $inList = true;
            } elseif ($block['type'] !== 'li' && $inList) {
                $html .= '</ul>';
                $inList = false;
            }

            $text = $this->polish($block['text']);

            $html .= match ($block['type']) {
                'h2' => '<h2>'.e($text).'</h2>',
                'h3' => '<h3>'.e($text).'</h3>',
                'strong' => '<p><strong>'.e($text).'</strong></p>',
                'li' => '<li>'.$this->labelled($text).'</li>',
                default => '<p>'.$this->labelled($text).'</p>',
            };
        }

        return $html.($inList ? '</ul>' : '');
    }

    /**
     * "Gestion de la caisse : contrôle…" - a short label before a colon is
     * the item's title, in bold as in the house style.
     */
    private function labelled(string $text): string
    {
        if (preg_match('/^([^:.;]{3,60}?)\s?:\s+(\S.*)$/u', $text, $m) === 1 && preg_match('/^\d+\.\d+/u', $m[1]) !== 1
            && count(preg_split('/\s+/u', $m[1]) ?: []) <= 8) {
            return '<strong>'.e($m[1].' :').'</strong> '.e($m[2]);
        }
        if (preg_match('/^(\d+\.\d+\.?\s[^:]{3,60}?)\s?:\s+(\S.*)$/u', $text, $m) === 1) {
            return '<strong>'.e($m[1].' :').'</strong> '.e($m[2]);
        }

        return e($text);
    }

    /** Typography the extraction breaks: missing space after a comma, doubled spaces. */
    private function polish(string $text): string
    {
        $text = (string) preg_replace('/,(?=\p{L})/u', ', ', $text);
        $text = (string) preg_replace('/\s+([,.])(?=\s|$)/u', '$1', $text);
        $text = (string) preg_replace('/\bCi-\s+après\b/u', 'Ci-après', $text);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    }

    private function isMostlyUpperCase(string $line): bool
    {
        $letters = preg_match_all('/\p{L}/u', $line);
        $upper = preg_match_all('/\p{Lu}/u', $line);

        return $letters > 0 && $upper / $letters >= 0.6;
    }

    private function squash(string $line): string
    {
        return trim((string) preg_replace('/\s{2,}/u', ' ', $line));
    }
}
