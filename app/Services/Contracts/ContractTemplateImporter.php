<?php

namespace App\Services\Contracts;

use App\Enums\ContractTarget;
use App\Services\AiChatService;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Turns an uploaded contract model (.docx / .pdf / .txt / .html) into an editable
 * template: clean HTML plus detected {{variables}}.
 *
 * The AI only *proposes* a structure - the result always lands in the editor
 * for human review before being saved. If the AI is unavailable or answers
 * something unusable, we fall back to the raw extracted HTML so the upload is
 * never a dead end.
 */
class ContractTemplateImporter
{
    /** Tags kept in imported HTML. Everything else is unwrapped or dropped. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'blockquote',
    ];

    /** Tags dropped with their whole subtree. */
    private const FORBIDDEN_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg'];

    /**
     * Accepted variable key shape. Dots are allowed because models naturally
     * mirror the namespaced automatic variables (`contract.start_date`).
     */
    private const KEY_PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)*$/i';

    /**
     * Only this much text is sent to the AI. The AI's answer is a short list of
     * replacements, so its latency is driven by the input size - an unbounded
     * 100-page document would otherwise blow past the request time limit.
     */
    private const MAX_AI_CHARS = 40000;

    /** Seconds; kept well under the 120 s request limit set by the controller. */
    private const AI_TIMEOUT = 60;

    /** A list of replacements is small; a low cap also bounds worst-case latency. */
    private const AI_MAX_TOKENS = 4096;

    private const MAX_REPLACEMENTS = 300;

    /** Shorter fragments ("6", "de") are too ambiguous to replace everywhere. */
    private const MIN_FIND_LENGTH = 3;

    public function __construct(
        private readonly DocxExtractor $docx,
        private readonly PdfExtractor $pdf,
        private readonly AiChatService $ai,
        private readonly ContractVariableResolver $resolver,
        private readonly ContractTextStructurer $structurer,
    ) {}

    /**
     * @return array{body_html: string, manual_variables: list<array{key: string, label: string}>, used_ai: bool, warning: ?string}
     */
    public function import(UploadedFile $file, ContractTarget $target): array
    {
        $rawHtml = $this->sanitize($this->extract($file));

        if (trim(strip_tags($rawHtml)) === '') {
            throw new RuntimeException('Le document semble vide : aucun texte exploitable.');
        }

        try {
            $analysed = $this->analyse($rawHtml, $target);
        } catch (RuntimeException $e) {
            Log::warning('Contract template AI import failed', ['error' => $e->getMessage()]);

            return [
                'body_html' => $rawHtml,
                'manual_variables' => [],
                'used_ai' => false,
                'warning' => 'Le document a été importé tel quel : '.$e->getMessage()
                    .' Vous pouvez insérer les variables manuellement.',
            ];
        }

        return $analysed + ['used_ai' => true];
    }

    /**
     * Extract an uploaded contract, text untouched and structure rebuilt:
     * paragraphs, articles, lists. The title and the signature block are
     * returned apart, since the PDF prints both on its own.
     *
     * @return array{body_html: string, title: ?string, signatories: ?array<string, string>}
     *
     * @throws RuntimeException
     */
    public function toCleanDocument(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $text = match ($extension) {
            'pdf' => $this->pdf->toText((string) $file->getRealPath()),
            'txt' => (string) file_get_contents((string) $file->getRealPath()),
            default => null,
        };

        if ($text === null) {
            return ['body_html' => $this->toCleanHtml($file), 'title' => null, 'signatories' => null];
        }

        $document = $this->structurer->structure($text);
        $html = $this->tidy($this->sanitize($document['body_html']));

        if (trim(strip_tags($html)) === '') {
            throw new RuntimeException('Le document semble vide : aucun texte exploitable.');
        }

        return ['body_html' => $html, 'title' => $document['title'], 'signatories' => $document['signatories']];
    }

    /**
     * Extract an uploaded contract as clean HTML, text untouched: no AI, no
     * variable detection. Used when the document is edited as a final text.
     *
     * @throws RuntimeException
     */
    public function toCleanHtml(UploadedFile $file): string
    {
        $html = $this->promoteArticleHeadings($this->tidy($this->sanitize($this->extract($file))));

        if (trim(strip_tags($html)) === '') {
            throw new RuntimeException('Le document semble vide : aucun texte exploitable.');
        }

        return $html;
    }

    /**
     * A short paragraph starting with "Article 3" / "ARTICLE III" / "Art. 3"
     * is an article title: turn it into the heading the layout expects.
     */
    private function promoteArticleHeadings(string $html): string
    {
        return (string) preg_replace_callback(
            '/<p>((?:<(?:strong|b|u|em)>)*\s*(?:article|art\.)\s*(?:\d+|[IVXLC]+)\b.{0,120}?)<\/p>/iu',
            fn (array $m) => '<h2>'.trim(strip_tags($m[1])).'</h2>',
            $html,
        );
    }

    /**
     * Remove the layout noise extraction and AI output leave behind: empty
     * paragraphs, runs of line breaks, breaks at the edges of a paragraph.
     */
    public function tidy(string $html): string
    {
        $html = (string) preg_replace('/(<br\s*\/?>\s*){2,}/i', '<br>', $html);
        $html = (string) preg_replace('/<(p|h[1-4]|li)>\s*(?:<br\s*\/?>\s*)+/i', '<$1>', $html);
        $html = (string) preg_replace('/(?:\s*<br\s*\/?>)+\s*<\/(p|h[1-4]|li)>/i', '</$1>', $html);
        $html = (string) preg_replace('/<(p|h[1-4]|li)>(?:\s|&nbsp;|\xC2\xA0)*<\/\1>/iu', '', $html);

        return trim($html);
    }

    private function extract(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'docx' => $this->docx->toHtml($path),
            'pdf' => $this->textToHtml($this->pdf->toText($path)),
            'txt' => $this->textToHtml((string) file_get_contents($path)),
            'html', 'htm' => (string) file_get_contents($path),
            default => throw new RuntimeException(
                'Format non pris en charge. Utilisez un fichier .docx, .pdf, .txt ou .html.'
            ),
        };
    }

    private function textToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        return implode('', array_map(
            fn (string $p) => '<p>'.nl2br(e(trim($p))).'</p>',
            array_filter($paragraphs, fn (string $p) => trim($p) !== '')
        ));
    }

    /**
     * Ask the AI which fragments of the text are variable, then apply those
     * replacements to the HTML ourselves.
     *
     * The AI never rewrites the document: it only returns a short list of
     * `{find, key}` pairs. That keeps the call fast whatever the document
     * length (the output no longer scales with it), and guarantees the contract
     * text itself is never altered or embellished by the model.
     *
     * @return array{body_html: string, manual_variables: list<array{key: string, label: string}>, warning: ?string}
     */
    private function analyse(string $html, ContractTarget $target): array
    {
        $catalog = $this->resolver->catalog($target);

        $text = $this->htmlToText($html);
        $truncated = mb_strlen($text) > self::MAX_AI_CHARS;
        if ($truncated) {
            $text = $this->truncateAtParagraph($text, self::MAX_AI_CHARS);
        }

        $raw = $this->ai->complete(
            $this->buildPrompt($catalog),
            "Texte du modèle de contrat :\n\n".$text,
            self::AI_MAX_TOKENS,
            self::AI_TIMEOUT,
        );

        $plan = $this->parsePlan($raw);

        [$bodyHtml, $appliedKeys, $unmatched] = $this->applyReplacements($html, $plan['replacements'], $plan['contextual']);

        return [
            'body_html' => $bodyHtml,
            'manual_variables' => $this->manualVariables($appliedKeys, $plan['labels'], $catalog),
            'warning' => $this->buildWarning($truncated, $unmatched, count($appliedKeys)),
        ];
    }

    /**
     * @param  array<string, string>  $catalog
     */
    private function buildPrompt(array $catalog): string
    {
        $catalogList = implode("\n", array_map(
            fn (string $label, string $key) => "- {{{$key}}} : {$label}",
            $catalog,
            array_keys($catalog)
        ));

        return <<<PROMPT
Tu analyses le texte d'un modèle de contrat pour le transformer en gabarit réutilisable.

## Ta tâche
Repère les informations propres à une personne ou à un cas particulier (nom, adresse, pièce d'identité,
dates, montants, durées, lieux, intitulé de formation…) et indique par quelle variable chacune doit être
remplacée. Tu NE réécris PAS le contrat : tu renvoies seulement la liste des fragments à remplacer.

## Variables automatiques disponibles (à utiliser EN PRIORITÉ quand elles correspondent)
{$catalogList}

## Règles absolues
- Réponds UNIQUEMENT par un objet JSON valide, sans texte autour, sans bloc de code.
- Forme exacte : {"replacements":[{"find":"...","key":"...","context":"... (optionnel)"}],"manual_variables":[{"key":"...","label":"..."}]}
- `find` est une copie EXACTE, caractère pour caractère, d'un fragment du texte fourni (même casse, mêmes
  accents). Il désigne UNIQUEMENT la valeur variable : jamais un article, une préposition ou une unité
  voisine (« de », « le », « mois », « FCFA »…). Exemples : "350 000", "1er novembre 2026", "Koudougou".
- Un `find` d'au moins 3 caractères sans ambiguïté est remplacé partout où il apparaît.
- Si la valeur est courte (ex: "5") OU si le même fragment a des sens différents selon l'endroit (ex: la
  ville "Ouagadougou" pour l'adresse de l'organisation, puis pour le lieu de signature), ajoute `context` :
  une copie EXACTE d'un passage plus long du texte qui contient `find` (ex: "avant le 5 de chaque mois").
  La variable ne remplace alors la valeur QUE dans ce passage. Un `context` par occurrence concernée.
- `find` désigne une information variable, JAMAIS une phrase de clause. Pas de doublon.
- Les préfixes `trainer.`, `cme.`, `partner.` et `org.` sont RÉSERVÉS aux variables automatiques listées
  ci-dessus : n'invente jamais une clé avec ces préfixes. Si l'information n'est pas dans la liste, c'est
  une variable manuelle.
- Une clé manuelle est un nom simple en snake_case, SANS point (ex: `montant_mensuel`, `date_debut`,
  `duree_mois`, `lieu_signature`) ; déclare-la dans `manual_variables` avec un `label` français court.
- Si rien n'est variable, réponds {"replacements":[],"manual_variables":[]}.
PROMPT;
    }

    /**
     * @return array{
     *     replacements: array<string, string>,
     *     contextual: list<array{find: string, key: string, context: string}>,
     *     labels: array<string, string>
     * }
     */
    private function parsePlan(string $raw): array
    {
        $json = $this->decodeJson($raw);

        if (! is_array($json['replacements'] ?? null)) {
            throw new RuntimeException("la réponse de l'IA était inexploitable.");
        }

        $replacements = [];
        $contextual = [];

        foreach ($json['replacements'] as $item) {
            if (count($replacements) + count($contextual) >= self::MAX_REPLACEMENTS) {
                break;
            }
            if (! is_array($item)) {
                continue;
            }

            $find = is_string($item['find'] ?? null) ? $this->normalizeSpaces($item['find']) : '';
            $key = is_string($item['key'] ?? null) ? trim($item['key']) : '';
            $context = is_string($item['context'] ?? null) ? $this->normalizeSpaces($item['context']) : '';

            if ($find === '' || $key === '' || preg_match(self::KEY_PATTERN, $key) !== 1
                || str_contains($find, '{{') || str_contains($context, '{{')) {
                continue;
            }

            if ($context !== '') {
                // A context only makes sense if it really contains the value.
                if (mb_stripos($context, $find) !== false) {
                    $contextual[] = ['find' => $find, 'key' => $key, 'context' => $context];
                }

                continue;
            }

            // Context-free replacements apply everywhere, so very short values
            // ("5", "de") would corrupt the text: they need a context instead.
            if (mb_strlen($find) >= self::MIN_FIND_LENGTH) {
                $replacements[$find] ??= $key;
            }
        }

        $labels = [];
        foreach ($json['manual_variables'] ?? [] as $variable) {
            if (is_array($variable) && is_string($variable['key'] ?? null) && is_string($variable['label'] ?? null)
                && trim($variable['label']) !== '') {
                $labels[trim($variable['key'])] = trim($variable['label']);
            }
        }

        return ['replacements' => $replacements, 'contextual' => $contextual, 'labels' => $labels];
    }

    /**
     * Apply the replacements on the text nodes of the HTML.
     *
     * Matching is done per text node, so a fragment that the document splits
     * across two formatting runs (half bold, half plain) cannot match and is
     * reported as unmatched rather than silently lost.
     *
     * @param  array<string, string>  $replacements  find => key, applied everywhere
     * @param  list<array{find: string, key: string, context: string}>  $contextual  applied inside their context only
     * @return array{0: string, 1: list<string>, 2: list<string>} html, applied keys, unmatched fragments
     */
    private function applyReplacements(string $html, array $replacements, array $contextual = []): array
    {
        if ($replacements === [] && $contextual === []) {
            return [$html, [], []];
        }

        $fragment = $this->loadFragment($html);
        $everywhere = $this->buildEverywherePattern($replacements);
        if ($fragment === null || ($replacements !== [] && $everywhere === null)) {
            $all = array_merge(array_keys($replacements), array_column($contextual, 'find'));

            return [$html, [], array_values($all)];
        }
        [$dom, $root] = $fragment;

        $appliedKeys = [];
        $appliedFinds = [];
        $appliedContextual = [];

        foreach ((new \DOMXPath($dom))->query('.//text()', $root) as $node) {
            $value = $node->nodeValue ?? '';
            if ($value === '') {
                continue;
            }

            // Contextual values first: they are the precise ones, and a broader
            // everywhere-replacement must not consume their surroundings.
            $replaced = $this->applyContextual($value, $contextual, $appliedKeys, $appliedContextual);

            if ($everywhere !== null) {
                $replaced = $this->applyEverywhere($replaced, $everywhere, $replacements, $appliedKeys, $appliedFinds);
            }

            if ($replaced !== $value) {
                $node->nodeValue = $replaced;
            }
        }

        $unmatched = array_merge(
            array_diff(array_keys($replacements), array_keys($appliedFinds)),
            array_values(array_map(
                fn (array $c) => $c['find'],
                array_filter($contextual, fn (array $c) => ! isset($appliedContextual[$c['context']."\0".$c['find']]))
            ))
        );

        return [
            $this->serializeChildren($dom, $root),
            array_keys($appliedKeys),
            array_values($unmatched),
        ];
    }

    /**
     * One alternation for every context-free fragment, longest first so
     * "Jean-Baptiste OUEDRAOGO" wins over "OUEDRAOGO".
     *
     * @param  array<string, string>  $replacements
     */
    private function buildEverywherePattern(array $replacements): ?string
    {
        if ($replacements === []) {
            return null;
        }

        $finds = array_keys($replacements);
        usort($finds, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        // Existing {{tokens}} are matched first and left untouched, so a
        // fragment can never be found inside a variable that was just inserted.
        $pattern = '/(\{\{[^}]*\}\})|'.$this->boundedAlternation($finds).'/u';

        return @preg_match($pattern, '') === false ? null : $pattern;
    }

    /**
     * @param  list<string>  $finds
     */
    private function boundedAlternation(array $finds): string
    {
        $alternatives = array_map(
            // preg_quote leaves spaces alone; make them tolerant of NBSP and runs.
            fn (string $find) => str_replace(' ', '[\s\x{00A0}]+', preg_quote($find, '/')),
            $finds
        );

        return '(?<![\p{L}\p{N}])(?:'.implode('|', $alternatives).')(?![\p{L}\p{N}])';
    }

    /**
     * @param  array<string, string>  $replacements
     * @param  array<string, true>  $appliedKeys
     * @param  array<string, true>  $appliedFinds
     */
    private function applyEverywhere(string $value, string $pattern, array $replacements, array &$appliedKeys, array &$appliedFinds): string
    {
        $replaced = preg_replace_callback($pattern, function (array $m) use ($replacements, &$appliedKeys, &$appliedFinds): string {
            if (($m[1] ?? '') !== '') {
                return $m[0]; // an already-inserted token
            }

            $find = $this->normalizeSpaces($m[0]);
            $key = $replacements[$find] ?? null;

            if ($key === null) {
                return $m[0];
            }

            $appliedKeys[$key] = true;
            $appliedFinds[$find] = true;

            return '{{'.$key.'}}';
        }, $value);

        return $replaced ?? $value;
    }

    /**
     * Replace `find` by its variable only inside the passage `context`, leaving
     * every neighbouring word untouched.
     *
     * @param  list<array{find: string, key: string, context: string}>  $contextual
     * @param  array<string, true>  $appliedKeys
     * @param  array<string, true>  $appliedContextual
     */
    private function applyContextual(string $value, array $contextual, array &$appliedKeys, array &$appliedContextual): string
    {
        foreach ($contextual as $item) {
            $contextPattern = '/'.str_replace(' ', '[\s\x{00A0}]+', preg_quote($item['context'], '/')).'/u';
            $findPattern = '/'.$this->boundedAlternation([$item['find']]).'/u';

            $replaced = preg_replace_callback($contextPattern, function (array $m) use ($item, $findPattern, &$appliedKeys, &$appliedContextual): string {
                $inside = preg_replace($findPattern, '{{'.$item['key'].'}}', $m[0], 1, $count);

                if ($inside === null || $count === 0) {
                    return $m[0];
                }

                $appliedKeys[$item['key']] = true;
                $appliedContextual[$item['context']."\0".$item['find']] = true;

                return $inside;
            }, $value);

            $value = $replaced ?? $value;
        }

        return $value;
    }

    /**
     * Keys used by at least one applied replacement and not already automatic
     * become manual variables, labelled by the AI or else derived from the key.
     *
     * @param  list<string>  $appliedKeys
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $catalog
     * @return list<array{key: string, label: string}>
     */
    private function manualVariables(array $appliedKeys, array $labels, array $catalog): array
    {
        $manual = [];

        foreach ($appliedKeys as $key) {
            if (array_key_exists($key, $catalog)) {
                continue;
            }

            $manual[] = ['key' => $key, 'label' => $labels[$key] ?? $this->humanizeKey($key)];
        }

        return $manual;
    }

    /**
     * @param  list<string>  $unmatched
     */
    private function buildWarning(bool $truncated, array $unmatched, int $appliedCount): ?string
    {
        $notes = [];

        if ($truncated) {
            $notes[] = 'Le document est très long : seuls les '.number_format(self::MAX_AI_CHARS, 0, ',', ' ')
                .' premiers caractères ont été analysés. Vérifiez les variables dans la suite du texte.';
        }

        if ($unmatched !== []) {
            $sample = implode(', ', array_map(fn (string $f) => '« '.$f.' »', array_slice($unmatched, 0, 3)));
            $notes[] = count($unmatched)." information(s) repérée(s) n'ont pas pu être remplacées"
                .' (mise en forme à cheval sur plusieurs styles) : '.$sample
                .(count($unmatched) > 3 ? '…' : '').'. Remplacez-les à la main.';
        }

        if ($appliedCount === 0 && ! $truncated) {
            $notes[] = "Aucune information variable n'a été détectée : insérez les variables à la main.";
        }

        return $notes === [] ? null : implode(' ', $notes);
    }

    /** Block-aware plain text of the HTML: one line per paragraph / row. */
    private function htmlToText(string $html): string
    {
        $text = preg_replace('#</(?:p|h[1-4]|li|tr|blockquote|table)>|<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(?:td|th)>#i', "\t", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Cut at the last paragraph break so a sentence is not sliced in half. */
    private function truncateAtParagraph(string $text, int $max): string
    {
        $cut = mb_substr($text, 0, $max);
        $lastBreak = mb_strrpos($cut, "\n");

        return ($lastBreak !== false && $lastBreak > $max * 0.8) ? mb_substr($cut, 0, $lastBreak) : $cut;
    }

    private function normalizeSpaces(string $value): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value));
    }

    /**
     * "contract.monthly_salary" → "Monthly salary" - a readable starting point
     * for a label the user can rewrite in the editor.
     */
    private function humanizeKey(string $key): string
    {
        $last = str_contains($key, '.')
            ? substr($key, (int) strrpos($key, '.') + 1)
            : $key;

        return ucfirst(trim(str_replace('_', ' ', $last)));
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJson(string $raw): array
    {
        $cleaned = trim($raw);

        // Models often wrap JSON in a ```json fence despite being told not to.
        if (str_starts_with($cleaned, '```')) {
            $cleaned = (string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $cleaned);
        }

        // Be tolerant of a stray sentence before/after the object.
        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $cleaned = substr($cleaned, $start, $end - $start + 1);
        }

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("la réponse de l'IA n'était pas du JSON valide.");
        }

        return $decoded;
    }

    /**
     * Strip everything outside the tag allowlist and remove every attribute.
     * Imported HTML is rendered raw (PDF and preview), so it must not be able
     * to carry scripts or event handlers.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $fragment = $this->loadFragment($html);
        if ($fragment === null) {
            return '';
        }
        [$dom, $root] = $fragment;

        $this->cleanNode($root);

        return $this->serializeChildren($dom, $root);
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}|null
     */
    private function loadFragment(string $html): ?array
    {
        $dom = new DOMDocument;
        $wrapped = '<?xml encoding="UTF-8"?><div id="__root">'.$html.'</div>';

        if (! @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return null;
        }

        $root = $dom->getElementById('__root');

        return $root instanceof DOMElement ? [$dom, $root] : null;
    }

    private function serializeChildren(DOMDocument $dom, DOMElement $root): string
    {
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private function cleanNode(DOMNode $node): void
    {
        // Snapshot children: the list mutates while we unwrap/remove.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::FORBIDDEN_TAGS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->cleanNode($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $child->removeAttribute($attribute->nodeName);
            }
        }
    }

    /** Replace an element by its own children, keeping the text. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
