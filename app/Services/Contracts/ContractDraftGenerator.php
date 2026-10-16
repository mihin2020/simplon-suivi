<?php

namespace App\Services\Contracts;

use App\Enums\ContractTarget;
use App\Services\AiChatService;
use RuntimeException;

/**
 * AI-assisted drafting of a contract, in three moves the user chains:
 * clarifying questions, a first draft, then as many revisions as needed.
 *
 * The contract is written for one known recipient, with real values - no
 * placeholders. The AI only ever *proposes*: every result lands in the editor
 * for human review, and its HTML goes through the import sanitiser.
 */
class ContractDraftGenerator
{
    private const MAX_QUESTIONS = 6;

    private const QUESTIONS_MAX_TOKENS = 1024;

    private const DRAFT_MAX_TOKENS = 8192;

    /** Typical length of a full contract answer (JSON-wrapped HTML), used to turn received characters into a percentage. */
    private const EXPECTED_DRAFT_CHARS = 14000;

    public function __construct(
        private readonly AiChatService $ai,
        private readonly ContractTemplateImporter $importer,
    ) {}

    /**
     * Questions whose answers would make the draft more accurate.
     *
     * @param  array<string, string>  $facts  what is already known about the recipient (label => value)
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function questions(ContractTarget $target, string $prompt, array $facts): array
    {
        $system = <<<PROMPT
Tu aides à rédiger un document de type « {$target->label()} » pour un organisme de formation au Burkina Faso.
À partir de la demande de l'utilisateur, pose les questions de précision réellement utiles pour rédiger le contrat
(durée, dates, rémunération, lieu, obligations particulières, clauses spécifiques, identité de l'organisme signataire…).

Règles :
- Au maximum 6 questions, courtes, en français, sans doublon.
- Ne demande ni ce qui est déjà dit dans la demande, ni ce qui figure dans les informations connues ci-dessous.
- Le contrat s'adresse à une personne ou à un organisme : si son identité n'est ni dans la demande ni dans les
  informations connues, demande-la. Pour une personne : nom et prénoms, date et lieu de naissance, adresse, et la
  pièce d'identité (CNIB ou passeport, numéro, date et lieu d'établissement, date d'expiration) ; regroupe ces
  éléments en une ou deux questions. Pour un organisme : raison sociale, adresse, représentant et sa fonction.
- Si tout est déjà précisé, renvoie une liste vide.
- Réponds UNIQUEMENT par un objet JSON : {"questions": ["...", "..."]}

{$this->factsBlock($facts)}
PROMPT;

        $json = $this->importer->decodeJson($this->ai->complete($system, $prompt, self::QUESTIONS_MAX_TOKENS));

        return collect($json['questions'] ?? [])
            ->filter(fn ($question) => is_string($question) && trim($question) !== '')
            ->map(fn (string $question) => trim($question))
            ->take(self::MAX_QUESTIONS)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $facts
     * @param  list<array{question: string, answer?: ?string}>  $answers
     * @param  (callable(int): void)|null  $onProgress  receives a percentage (0–99) while the AI writes
     * @return array{body_html: string, signatories: array<string, string>}
     *
     * @throws RuntimeException
     */
    public function draft(ContractTarget $target, string $prompt, array $facts, array $answers = [], ?callable $onProgress = null): array
    {
        $message = "Demande de l'utilisateur :\n".$prompt;

        $answered = array_filter($answers, fn (array $item) => trim((string) ($item['answer'] ?? '')) !== '');
        if ($answered !== []) {
            $message .= "\n\nPrécisions apportées :\n".implode("\n", array_map(
                fn (array $item) => '- '.$item['question'].' → '.$item['answer'],
                $answered,
            ));
        }

        return $this->produce($target, 'Rédige le document complet demandé.', $message, $facts, self::EXPECTED_DRAFT_CHARS, $onProgress);
    }

    /**
     * Put an imported document into the house layout: same content, but the
     * structure and formatting rules below (articles, paragraphs, signatures)
     * instead of whatever layout the source file had.
     *
     * @param  array<string, string>  $facts
     * @param  (callable(int): void)|null  $onProgress  receives a percentage (0–99) while the AI writes
     * @return array{body_html: string, signatories: array<string, string>}
     *
     * @throws RuntimeException
     */
    public function reformat(ContractTarget $target, string $importedHtml, array $facts, ?callable $onProgress = null): array
    {
        return $this->produce(
            $target,
            'Remets en forme le document importé ci-dessous selon les règles de mise en forme. Le FOND ne change pas : '
                .'conserve toutes les clauses, montants, dates et noms, dans le même ordre, sans résumer, sans ajouter de '
                .'clause ni reformuler le sens. Corrige seulement la structure (titres d\'articles, paragraphes, listes), '
                .'la ponctuation et les coupures de lignes héritées du fichier. Si le document n\'a pas d\'articles, '
                .'découpe-le en articles logiques sans modifier le texte. Le bloc de signatures du document est retiré du '
                .'texte et reporté dans `signatories`.',
            "Document importé (HTML brut) :\n".$this->importer->sanitize($importedHtml),
            $facts,
            (int) (mb_strlen($importedHtml) * 1.1) + 200,
            $onProgress,
        );
    }

    /**
     * @param  array<string, string>  $facts
     * @param  (callable(int): void)|null  $onProgress  receives a percentage (0–99) while the AI writes
     * @return array{body_html: string, signatories: array<string, string>}
     *
     * @throws RuntimeException
     */
    public function revise(ContractTarget $target, string $currentHtml, string $instruction, array $facts, ?callable $onProgress = null): array
    {
        $message = "Document actuel (HTML) :\n".$this->importer->sanitize($currentHtml)
            ."\n\nModification demandée :\n".$instruction;

        return $this->produce(
            $target,
            'Applique la modification demandée au document actuel et renvoie le document complet. '
                .'Ne change rien d\'autre : conserve tel quel le reste du texte, sa structure et sa numérotation '
                .'(renumérote les articles seulement si tu en ajoutes ou en retires).',
            $message,
            $facts,
            // The whole document comes back: its current length is the expected size.
            (int) (mb_strlen($currentHtml) * 1.1) + 200,
            $onProgress,
        );
    }

    /**
     * @param  array<string, string>  $facts
     * @return array{body_html: string, signatories: array<string, string>}
     */
    private function produce(ContractTarget $target, string $task, string $message, array $facts, int $expectedChars, ?callable $onProgress): array
    {
        $system = <<<PROMPT
Tu es juriste-rédacteur chez SIMPLON BURKINA FASO. Tu rédiges un « {$target->label()} » selon les usages de la maison,
en droit burkinabè, dans un français juridique soutenu, précis et sans faute d'orthographe.

## Ta tâche
{$task}

## Nature du contrat
Déduis-la de la demande, le type ci-dessus n'en décide pas : un formateur ou un chargé de mission peut être salarié
(CDI, CDD) ou prestataire indépendant.
- CONTRAT DE TRAVAIL (CDI / CDD) : parties « l'employeur » et « le travailleur ». Articles habituels, dans cet ordre :
  Engagement ; Tâches (liste, puis « toute autre tâche ponctuelle relevant de sa qualification professionnelle ») ;
  Droit applicable ; Nature et durée du contrat ; Période d'essai ; Classification professionnelle et rémunération
  (salaire brut décomposé : base et indemnités, retenues fiscales, CNSS) ; Durée hebdomadaire et horaire de travail ;
  Heures supplémentaires ; Lieu de travail et clause de mobilité ; Clause de confidentialité ; Repos hebdomadaire
  et congé annuel ; Obligations des parties ; Préavis et rupture du contrat ; Déclaration de disponibilité ;
  Règlement des litiges (amiable, puis procédure du code du travail) ; Dispositions finales.
  Textes de référence à citer tels quels dans « Droit applicable » :
  loi n° 028-2008/AN du 13 mai 2008 portant code du travail au Burkina Faso et ses textes d'application ;
  convention collective interprofessionnelle du 9 juillet 1974 ; décret n° 2010-812/PRES/PM/MTSS portant conditions
  de travail des travailleurs des professions et branches d'activités non régies par une convention collective.
  N'invente aucune autre référence juridique ni aucun numéro d'article.
- CONTRAT DE PRESTATION DE SERVICES : parties « LE CLIENT » (Simplon) et « LE PRESTATAIRE ». Articles habituels :
  Objet du contrat ; Description des missions (par axes en sous-parties 2.1, 2.2…) ; Obligations du Client ;
  Durée du contrat (renouvelable par avenant écrit) ; Conditions financières et modalités de paiement (forfait,
  facture mensuelle mentionnant l'IFU, délai et mode de règlement, conditions liées aux livrables) ; Nature de la
  relation (indépendance, aucun lien de subordination ni contrat de travail) ; Confidentialité et protection des
  données ; Résiliation (mise en demeure écrite) ; Règlement des litiges et droit applicable (tribunaux de Ouagadougou).
- CONVENTION DE PARTENARIAT : parties désignées par leur nom ; objet, engagements de chaque partie, durée,
  confidentialité, résiliation, litiges.
Ces listes guident, elles ne s'imposent pas : la demande de l'utilisateur prime.

## Simplon Burkina Faso (toujours l'une des parties)
{$this->organisationBlock()}

## Contenu
- Le document est DÉFINITIF et destiné à être signé : écris les vraies informations, jamais de variable ni
  d'accolades, jamais de « [à compléter] » inventé.
- Utilise les informations connues ci-dessous telles quelles (orthographe des noms comprise). Pour un document
  importé, les valeurs écrites dans le document font foi ; une information de l'autre partie absente du document
  (ou laissée en blanc) est complétée avec les informations connues.
- Une information indispensable et réellement inconnue est remplacée par une ligne de pointillés « ……………… »
  à remplir à la main. N'invente aucun montant, date, numéro ou identité.

{$this->factsBlock($facts)}

## Mise en forme : à respecter rigoureusement
- N'écris pas le titre du document : il est ajouté à part. Commence directement par :
  <p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
- Désignation de Simplon : un paragraphe commençant par <strong>SIMPLON BURKINA FASO</strong>, avec forme juridique,
  RCCM, IFU, adresse, téléphone, email, « représenté par Monsieur … en sa qualité de Représentant pays, dûment habilité
  aux fins des présentes », puis <p>Ci-après dénommé « … »</p> et <p><strong>D'une part,</strong></p>.
- Désignation de l'autre partie, puis <p>Ci-après dénommé(e) « … »</p> et <p><strong>D'autre part,</strong></p>.
  Pour un salarié, une ligne par information (avec <br>) : nom et prénoms, sexe, date et lieu de naissance, nationalité,
  situation matrimoniale, profession, résidence, contact, pièce d'identité (type, numéro, date et lieu
  d'établissement, date d'expiration). Pour un prestataire : raison sociale ou nom, régime fiscal, IFU, adresse,
  représentant et sa qualité.
- Puis <p><strong>IL A ÉTÉ ARRÊTÉ ET CONVENU CE QUI SUIT :</strong></p>
- Chaque article : un <h2> au format exact « Article 1 : Engagement » (numérotation continue, deux-points, titre court
  sans point final). Sous-parties : <h3> au format « 2.1. Sourcing et relations partenaires ».
- Texte en <p>, un paragraphe par idée, phrases complètes. Énumérations en <ul> ; quand un élément a un intitulé, il
  est en gras suivi de deux-points : <li><strong>Gestion de la caisse :</strong> contrôle des entrées et sorties.</li>
- Nombres et durées en lettres puis en chiffres entre parenthèses : « trois (03) mois », « quarante (40) heures »,
  « deux (02) exemplaires ». Montants en lettres puis en chiffres, en gras pour le chiffre :
  « Cent cinquante mille francs CFA net (<strong>150 000 F CFA</strong>) » ; dans un détail de salaire :
  « Salaire de base : <strong>246 184 FCFA</strong> ». Séparateur des milliers : une espace.
- Dates : « 1er juin 2023 », « 09 juin 2026 ».
- Dernier paragraphe : « Fait et signé à Ouagadougou, le ………………, en deux (02) exemplaires originaux. »
  N'écris PAS le bloc de signatures dans le texte : il est imprimé à part, sur deux colonnes, à partir de `signatories`.
- Aucun paragraphe vide, aucun double saut de ligne, aucun en-tête ni pied de page.

## Format de réponse
- Réponds UNIQUEMENT par un objet JSON valide, sans texte autour :
  {"body_html": "...", "signatories": {"left_title": "...", "left_name": "...", "right_title": "...", "right_name": "..."}}
- `signatories` : à gauche Simplon (« Pour SIMPLON BURKINA FASO » ou « L'employeur », et le nom du représentant),
  à droite l'autre partie (« Pour le Prestataire », « Le travailleur », « Pour [nom du partenaire] », et son nom ;
  « ……………… » si inconnu).
- `body_html` n'utilise que ces balises, sans aucun attribut : p, br, strong, em, u, h2, h3, ul, ol, li.
PROMPT;

        $raw = $onProgress === null
            ? $this->ai->complete($system, $message, self::DRAFT_MAX_TOKENS)
            : $this->ai->streamComplete($system, $message, self::DRAFT_MAX_TOKENS, function (int $received) use ($onProgress, $expectedChars) {
                // Never 100 % before the answer is parsed and checked.
                $onProgress(min(99, (int) floor($received / max(1, $expectedChars) * 100)));
            });

        $json = $this->importer->decodeJson($raw);

        $bodyHtml = is_string($json['body_html'] ?? null)
            ? $this->importer->tidy($this->importer->sanitize($json['body_html']))
            : '';

        if (trim(strip_tags($bodyHtml)) === '') {
            throw new RuntimeException("la réponse de l'IA était inexploitable.");
        }

        return ['body_html' => $bodyHtml, 'signatories' => $this->signatories($json['signatories'] ?? null)];
    }

    /**
     * The four texts of the signature block, plain strings only.
     *
     * @return array<string, string>
     */
    private function signatories(mixed $value): array
    {
        $organisation = config('contracts.organisation');
        $defaults = [
            'left_title' => 'Pour '.$organisation['name'],
            'left_name' => (string) $organisation['representative'],
            'right_title' => 'Pour l\'autre partie',
            'right_name' => '………………',
        ];

        $signatories = [];
        foreach ($defaults as $key => $default) {
            $text = is_array($value) && is_string($value[$key] ?? null) ? trim(strip_tags($value[$key])) : '';
            $signatories[$key] = mb_substr($text !== '' ? $text : $default, 0, 120);
        }

        return $signatories;
    }

    private function organisationBlock(): string
    {
        $o = config('contracts.organisation');

        return implode("\n", [
            "- Dénomination : {$o['name']}, {$o['legal_form']}, ayant pour activité principale {$o['activity']}",
            "- RCCM : {$o['rccm']} ; IFU : {$o['ifu']}",
            "- Siège : {$o['address']}",
            "- Téléphone : {$o['phone']} ; email : {$o['email']}",
            "- Représentant : {$o['representative']}, en sa qualité de {$o['representative_title']}",
            "- Lieu de signature habituel : {$o['city']}",
        ]);
    }

    /**
     * @param  array<string, string>  $facts
     */
    private function factsBlock(array $facts): string
    {
        if ($facts === []) {
            return "## Informations connues\nAucune.";
        }

        return "## Informations connues\n".implode("\n", array_map(
            fn (string $label, string $value) => "- {$label} : {$value}",
            array_keys($facts),
            $facts,
        ));
    }
}
