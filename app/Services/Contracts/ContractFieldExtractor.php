<?php

namespace App\Services\Contracts;

use App\Enums\FillFieldsStatus;
use App\Models\Contract;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Finds the passages of a contract that change from one person to another
 * (identity, amounts, dates, position…). They become the columns of the
 * Excel model used to generate the contract for many people.
 *
 * Only passages found verbatim in the text are kept, so each one can be
 * replaced exactly when a row of the model is applied.
 */
class ContractFieldExtractor
{
    private const MAX_FIELDS = 25;

    private const MIN_LENGTH = 2;

    private const AI_MAX_TOKENS = 2048;

    /** Longest text sent to the AI, to keep the call fast. */
    private const MAX_AI_CHARS = 30000;

    public function __construct(
        private readonly ContractVariableResolver $resolver,
        private readonly AiChatService $ai,
        private readonly ContractTemplateImporter $importer,
    ) {}

    /**
     * Detect and store the fields. Never throws: without AI, the recipient's
     * known identity found in the text is still offered.
     */
    public function refresh(Contract $contract): void
    {
        $html = (string) $contract->body_html;
        $fields = $this->knownIdentity($contract, $html);

        try {
            $fields = $this->merge($fields, $this->detectWithAi($html));
        } catch (RuntimeException $e) {
            Log::warning('Contract field detection by AI failed', ['contract_id' => $contract->id, 'error' => $e->getMessage()]);
        }

        $contract->forceFill([
            'fill_fields' => array_slice($fields, 0, self::MAX_FIELDS),
            'fill_fields_status' => FillFieldsStatus::Ready,
        ])->saveQuietly();
    }

    /**
     * What the application knows about the recipient and that appears in the text.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    private function knownIdentity(Contract $contract, string $html): array
    {
        $contract->loadMissing('subject');
        if ($contract->subject === null) {
            return [];
        }

        $labels = $this->resolver->catalog($contract->target);
        $fields = [];

        foreach ($this->resolver->resolve($contract->target, $contract->subject) as $key => $value) {
            $value = trim($value);
            if ($key === 'today' || mb_strlen($value) < 3 || ! str_contains($html, e($value))) {
                continue;
            }

            $fields[] = ['key' => $key, 'label' => $this->shortLabel($labels[$key] ?? $key), 'value' => $value];
        }

        return $fields;
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     *
     * @throws RuntimeException
     */
    private function detectWithAi(string $html): array
    {
        $text = mb_substr(trim(html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '</h2>', '</h3>', '<br>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, self::MAX_AI_CHARS);

        $system = <<<'PROMPT'
Tu analyses un contrat rédigé pour UNE personne afin de le réutiliser pour d'autres personnes.
Repère les passages qui changeraient d'une personne à l'autre : identité (nom, prénoms, date et lieu de naissance,
adresse, pièce d'identité, téléphone, email), poste ou mission, montants, dates de début et de fin, durée, lieu
d'exercice, et toute autre donnée propre à cette personne.

Règles :
- `value` est copié EXACTEMENT tel qu'il apparaît dans le texte (mêmes majuscules, accents, espaces).
- Une même valeur n'apparaît qu'une fois dans la liste. Au maximum 20 éléments.
- N'inclus PAS ce qui est commun à tous les contrats (nom de l'organisme, clauses générales, titres d'articles).
- `label` est un intitulé de colonne court en français (ex : « Nom complet », « Montant mensuel »).
- Réponds UNIQUEMENT par un objet JSON : {"fields": [{"label": "...", "value": "..."}]}
PROMPT;

        $json = $this->importer->decodeJson($this->ai->complete($system, $text, self::AI_MAX_TOKENS));
        $fields = [];

        foreach ($json['fields'] ?? [] as $index => $field) {
            $value = is_array($field) ? trim((string) ($field['value'] ?? '')) : '';
            $label = is_array($field) ? trim((string) ($field['label'] ?? '')) : '';

            // Keep only what can really be found, hence replaced, in the text.
            if (mb_strlen($value) < self::MIN_LENGTH || $label === '' || ! str_contains($html, e($value))) {
                continue;
            }

            $fields[] = ['key' => 'ai_'.$index, 'label' => mb_substr($label, 0, 60), 'value' => $value];
        }

        return $fields;
    }

    /**
     * Known identity first, then the AI findings whose value is not already covered.
     *
     * @param  list<array{key: string, label: string, value: string}>  $known
     * @param  list<array{key: string, label: string, value: string}>  $detected
     * @return list<array{key: string, label: string, value: string}>
     */
    private function merge(array $known, array $detected): array
    {
        $values = array_map(fn (array $f) => mb_strtolower($f['value']), $known);

        foreach ($detected as $field) {
            if (! in_array(mb_strtolower($field['value']), $values, true)) {
                $known[] = $field;
                $values[] = mb_strtolower($field['value']);
            }
        }

        return $known;
    }

    /** "Formateur : nom complet" → "Nom complet". */
    private function shortLabel(string $label): string
    {
        $short = str_contains($label, ' : ') ? trim(substr($label, strrpos($label, ' : ') + 3)) : $label;

        return mb_strtoupper(mb_substr($short, 0, 1)).mb_substr($short, 1);
    }
}
