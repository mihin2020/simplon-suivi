# Module Contrats & conventions

Génération de documents contractuels : **contrats formateurs**, **contrats CME**
(Chargé Médiation Emploi) et **conventions de partenariat**.

> ⚠️ **Écart au CDC assumé.** Ce module n'est pas décrit dans
> `docs/CDC_Simplon_Burkina_v2.docx` (qui ne couvre que les *types* de contrat
> d'insertion des apprenants, l'upload du CV formateur et les feuilles
> d'émargement). Il a été demandé explicitement par le maître d'ouvrage en
> extension du périmètre initial.

---

> **Mise à jour du 2026-10-11 - à lire avant le reste.**
> - Les **modèles de contrat** n'ont plus d'écran (`/contract-templates` supprimé) et ne sont plus créés : le contenu
>   d'un contrat vient toujours d'un fichier importé ou de l'IA, dans l'assistant `/contracts/create` (§6ter).
>   La table `contract_templates` et le modèle `ContractTemplate` subsistent sans être alimentés.
> - L'**identité documentaire** (`document_settings`, page, logo, variables `org.*`) est supprimée (§7 obsolète).
>   Les informations de l'organisme s'écrivent dans le texte du contrat ou dans l'habillage.
> - En-tête et pied passent uniquement par les **habillages** (§6bis). Sans habillage, le PDF garde l'ancien cadre
>   (filet et numéro de page) sans contenu.
> - **Plus de variables `{{…}}`** : un contrat est rédigé pour **un seul destinataire**, choisi avant le contenu.
>   L'IA reçoit ce que l'application sait de lui (`ContractVariableResolver::facts()`) et écrit le texte définitif ;
>   un fichier importé est repris tel quel (`ContractTemplateImporter::toCleanHtml()`, sans IA). Plus de publipostage.
> - **Mise en forme** : la feuille de l'éditeur (`ContractDocumentEditor.vue`) et le PDF (`pdfs/contract.blade.php`)
>   partagent la même typographie (DejaVu Serif 11 pt, marges 20 mm, paragraphes justifiés, titres d'article en `<h2>`).
>   Toute modification de l'un doit être reportée dans l'autre. Le HTML est nettoyé à l'enregistrement (`tidy()`).
> - **Plus de verrou** (2026-10-12) : un contrat reste modifiable quel que soit son statut. « Finaliser » marque le
>   statut et archive le PDF ; toute modification ultérieure ré-archive le PDF. La section 5 est obsolète sur ce point.
> - **Projets de contrats** (`contract_projects`, `/contracts/projects`) : un projet (nom, description, type) regroupe
>   des contrats (`contracts.contract_project_id`). On y rédige un contrat de base, puis
>   `POST /contracts/{contract}/duplicate` (`DuplicateContract`) en crée une copie par personne cochée.
>   `ContractPersonalizer` remplace, sans IA, les informations connues du destinataire d'origine (nom, adresse, pièce…)
>   par celles du nouveau ; une information absente devient des pointillés. Les accords (« domiciliée », « Mme ») ne
>   sont pas adaptés. La même duplication est disponible depuis la fiche de tout contrat.
> - **Fiche contrat** : elle affiche le PDF réel dans la page (iframe sur `/contracts/{id}/pdf`).
> - **Modèle Excel par contrat** (2026-10-14) : après chaque enregistrement, le job `ExtractContractFields` repère
>   les passages propres à la personne (identité connue + détection IA, valeurs vérifiées présentes dans le texte) dans
>   `contracts.fill_fields`. `GET /contracts/{id}/fill-model` télécharge le modèle (une colonne par passage, ligne
>   d'exemple = contrat d'origine ; préparé à la demande si le job n'a pas tourné). `POST` du fichier rempli →
>   `GenerateContractsFromRows` : un contrat par ligne, passages remplacés par `strtr()`. Nécessite un worker de file
>   (`queue:listen`, lancé par `composer dev`) pour la préparation en arrière-plan.
> - **Destinataires** : `ContractSubjectDirectory` propose aussi les utilisateurs actifs de la plateforme (contrats
>   formateur et CME) ; un `User` peut être le sujet d'un contrat.
> - **IA en flux** : `draft`, `revise` et `reformat` répondent en NDJSON (`progress` puis `done`/`error`) ; le
>   pourcentage = caractères reçus / taille attendue (longueur du document pour une révision, estimation pour une
>   rédaction). Un contrat importé est remis en forme par l'IA (`reformat`), fond inchangé.
> - **PDF** : un seul rendu (numéros de page dessinés par `page_text`), mis en cache sur le disque privé tant que
>   `updated_at` ne change pas.
> - **Actions groupées** : sélection dans la liste et dans un projet → ZIP des PDF (`POST /contracts/bulk/download`),
>   suppression (`/bulk/delete`), envoi par email (`/bulk/email`, via `EmailService`) : chacun reçoit son contrat
>   (adresse issue du destinataire) ou un seul email avec tous les contrats. Recherche `Contract::search()`
>   (référence, titre, nom du destinataire). Le dépôt du scan signé est supprimé de l'interface.
> - **Identité du destinataire** : pas de formulaire dédié. Quand l'identité n'est ni dans la demande ni dans la fiche
>   du destinataire, l'IA la demande dans ses questions (personne : nom, naissance, adresse, pièce d'identité avec
>   numéro, date et lieu d'établissement, expiration ; organisme : raison sociale, adresse, représentant). Les dates des
>   contrats sont écrites en français (`locale('fr')`), indépendamment d'`APP_LOCALE`.
> - **CME** supprimés (table, pages, permissions) ; le type « Contrat chargé médiation emploi » reste, ses destinataires
>   étant les utilisateurs de la plateforme.
> - **Style maison** (2026-10-15, d'après deux contrats réels de Simplon BF) : l'IA déduit la nature du contrat
>   (travail CDI/CDD, prestation, partenariat) de la demande, suit la structure et les clauses habituelles de chaque
>   nature, cite l'identité de Simplon (`config/contracts.php`), titre les articles « Article 1 : Objet » (gras souligné),
>   écrit les nombres « trois (03) mois » et les montants en lettres puis en chiffres. Le bloc de signatures n'est plus
>   dans le texte : `contracts.signatories` (4 libellés) est imprimé sur deux colonnes à la fin du PDF.
> - **Import restructuré** (2026-10-15) : le texte d'un PDF/.txt (une ligne par ligne visuelle) est reconstruit par
>   `ContractTextStructurer` - paragraphes recollés, « Article N : … » en h2, « 2.1. … » en h3, puces en listes,
>   numéros de page retirés ; le titre et le bloc de signatures sont extraits à part. Les listes Word deviennent des listes.
> - **Mise en page modifiable** : `contracts.letterhead_id` (en-tête lié, affiché et modifiable dans l'éditeur en mode
>   édition) et `contracts.font` (enum `ContractFont` : DejaVu, Poppins, Roboto, Open Sans, Lato, Montserrat,
>   Merriweather). Les .ttf sont dans `public/fonts/contracts/`, partagés par le PDF et l'éditeur.
> - Les sections 1, 2, 4, 7, 8, 11 et 12 décrivent en partie l'ancien fonctionnement.

## 1. Principe : deux niveaux

| Niveau | Table | Rôle |
|---|---|---|
| **Modèle** | `contract_templates` | Squelette réutilisable : corps HTML + variables + entête/pied optionnels |
| **Contrat généré** | `contracts` | Instance concrète pour une personne/un partenaire, **éditable individuellement** |

Générer un contrat **fige une copie** du modèle fusionné dans `contracts.body_html`.
Modifier ce contrat **ne touche jamais au modèle** ni aux autres contrats déjà générés.

## 2. Génération en lot (publipostage)

Un contrat se génère pour **un ou plusieurs destinataires** à la fois
(`subject_ids`, 200 maximum par lot) : on choisit un modèle, on coche les
personnes, et **un document distinct est créé pour chacune**, avec ses propres
variables automatiques.

- Les **valeurs manuelles** (montant, dates…) sont **communes à tout le lot** -
  c'est le principe du publipostage. Pour une valeur différente par personne,
  générer séparément ou ajuster chaque document ensuite (il reste éditable).
- Le lot est créé dans une **transaction** : un échec en cours de route ne
  laisse pas la moitié des contrats derrière lui.
- Les identifiants en doublon sont dédupliqués (3 fois la même personne = 1 contrat).
- Après génération : **1 contrat** → ouverture directe de l'éditeur ;
  **plusieurs** → retour à la liste, chaque document restant à relire.

## 3. Destinataire (sujet polymorphe)

`contracts.subject_type` / `subject_id` pointent vers l'entité concernée :

| Cible (`ContractTarget`) | Entité |
|---|---|
| `trainer` | `App\Models\Trainer` |
| `cme` | `App\Models\Cme` |
| `partner_convention` | `App\Models\Partner` |

`Cme` est une entité dédiée (individus, **sans accès à la plateforme**), gérée
via son propre CRUD sous `/contracts/cmes`.

### État civil du formateur
Les contrats formateur ont besoin de l'état civil, qui n'existait pas sur
`Trainer`. Quatre champs ont été ajoutés (`birth_date`, `birth_place`,
`address`, `id_number`) et se saisissent dans le formulaire **Utilisateurs**
(section « État civil », visible seulement pour le rôle Formateur), là où le
reste de la fiche formateur est déjà géré.

Sans eux, l'IA d'import inventait `{{trainer.birth_date}}` et la valeur devait
être retapée à chaque contrat.

## 4. Variables

Syntaxe dans le corps du modèle : `{{clé}}`.

**Deux sources :**

1. **Automatiques** - résolues depuis le destinataire et l'identité documentaire
   par `ContractVariableResolver` (ex. `{{cme.full_name}}`, `{{trainer.email}}`,
   `{{partner.name}}`, `{{org.name}}`, `{{today}}`).
   Le catalogue complet par cible est exposé à l'éditeur de modèles.
2. **Manuelles** - déclarées sur le modèle (`manual_variables`: `[{key, label}]`)
   et saisies au moment de générer (stockées dans `contracts.data`).

**La fusion est déterministe et sans IA** (`ContractMergeService`) : c'est un
remplacement de tokens, échappé via `e()`. Un token inconnu est **laissé visible**
dans le document, pour que l'oubli se voie au lieu de produire un trou silencieux.

## 5. Cycle de vie

```
brouillon ──finalize──> finalisé ──upload scan──> signé
   │                       │
   │ éditable              └─ PDF archivé (contracts.pdf_path)
   └─ corps / entête / pied modifiables directement
```

- **`brouillon`** : le document généré est librement modifiable (éditeur TipTap).
- **`finalisé`** : le PDF est rendu et archivé, le document est **verrouillé**.
- **`signé`** : le scan du contrat signé à la main est rattaché
  (`signed_scan_path`).

### Verrou d'immuabilité - point d'attention

L'immuabilité d'un contrat finalisé est un **invariant métier, pas une
permission** : elle est appliquée dans `ContractController::ensureEditable()`,
**pas** dans `ContractPolicy`.

Raison : `ContractPolicy::before()` accorde tout au Super Admin et
court-circuiterait la règle, permettant de réécrire silencieusement un document
déjà signé. Le verrou s'applique donc à **tout le monde**, Super Admin inclus.
Une correction passe par une nouvelle version, jamais par une réécriture.

## 6. PDF : entête, pied et multi-page

`ContractPdfService` → `resources/views/pdfs/contract.blade.php` → DomPDF.

- Entête et pied sont en `position: fixed` → **répétés sur chaque page**.
- Pagination via `content: counter(page) " / " counter(pages)`.
- Les marges `@page` réservent la place de l'entête/pied.
- Le multi-page est automatique : le corps s'écoule sur autant de pages que
  nécessaire (vérifié : un corps long rend 5 pages).

**Ordre de résolution** de l'entête/pied : valeur figée sur le contrat →
valeur du modèle → `document_settings` (défaut de l'organisation).

## 6bis. Habillages : en-tête + pied de page importés

Onglet **Contrats → En-têtes & pieds** (`/contracts/layouts`, permission `contracts.templates`).

- Un **habillage** (`letterheads`) = un nom + une image d'en-tête et/ou une image de pied (PNG/JPG/WebP, 2 Mo),
  conçues en dehors de l'application. `LetterheadService` les met à la largeur A4 (210 mm), hauteur proportionnelle,
  plafonnée à 80 mm (en-tête) / 60 mm (pied) pour garder la place du corps.
- **Lien facultatif** : `contract_templates.letterhead_id` + `letterhead_scope` (enum `LetterheadScope` :
  toutes les pages / première page uniquement). Le choix est proposé à la génération et peut différer du modèle.
- Sans habillage, le contrat garde l'ancien rendu (HTML du modèle ou de `document_settings`).
- À la génération, le rendu est **figé** sur le contrat : `header_html`, `footer_html`, `header_height_mm`,
  `footer_height_mm` (null = ancien rendu, 0 = côté vide) et `letterhead_scope`.
- PDF : marges `@page` dynamiques. « Toutes les pages » = bandeaux `position:fixed`. « Première page » = en-tête dans le
  flux et pied en `position:absolute` avec `@page :first`, les pages suivantes ayant une marge de 15 mm.
  Le total de pages est mesuré par une première passe (dompdf imprime `counter(pages)` à 0).

## 6ter. Assistant de génération (`/contracts/create`)

Quatre étapes : **type** → **contenu** → **en-tête & pied** → **destinataires**.

Le contenu vient d'un fichier importé (`POST /contract-templates/import`) ou de l'IA
(`POST /contract-templates/draft`, `ContractDraftGenerator`) :

- `action=questions` : l'IA pose jusqu'à 6 questions de précision à partir du prompt ;
- `action=draft` : rédaction du modèle (prompt + réponses), avec variables `{{…}}` ;
- `action=revise` : applique une consigne au texte courant - autant d'itérations que voulu, avec retour arrière côté écran.

Le HTML produit passe par le même assainisseur que l'import ; tout jeton inconnu du catalogue devient une variable
manuelle. Une fois validé, le contenu est fusionné et figé dans chaque contrat généré
(`StoreContractRequest` : `target`, `name`, `body_html`).

## 7. Identité documentaire

Table `document_settings` (ligne unique, via `DocumentSetting::current()`) :
logo, raison sociale, adresse, téléphone, email, IFU/RCCM, entête et pied par
défaut. Éditable sur `/contracts/document-settings`.

## 8. Permissions

| Slug | Portée |
|---|---|
| `contracts.view` | Consulter les contrats |
| `contracts.create` | Générer un contrat |
| `contracts.update` | Modifier / finaliser / rattacher le scan signé |
| `contracts.delete` | Supprimer (soft delete) |
| `contracts.templates` | Gérer les modèles **et** l'identité documentaire |
| `cmes.view` / `.create` / `.update` / `.delete` | CRUD des CME |

Policies : `ContractPolicy`, `ContractTemplatePolicy`, `CmePolicy`.

## 9. Signature

Choix retenu : **zone de signature sur le PDF + upload du scan**.
Pas de signature électronique (aucune dépendance externe).

## 10. Audit

Les actions sensibles (génération, finalisation, upload du scan signé) sont
journalisées via `Log::info` avec `contract_id`, `reference` et `user_id`.
Le projet n'a pas de table d'audit dédiée ; cette approche suit l'existant.

## 11. Import IA d'un modèle existant (phase 2 - livré)

Sur l'écran de création/édition d'un modèle, on peut uploader un modèle
existant : l'IA le convertit en gabarit éditable et repère les variables.

`POST /contract-templates/import` (JSON) → `{ body_html, manual_variables, used_ai, warning }`.
**Rien n'est enregistré** : le résultat pré-remplit l'éditeur pour relecture.

### Formats et extraction
| Format | Extraction |
|---|---|
| `.docx` | `DocxExtractor` - **`ZipArchive` + DOM natifs** (un .docx est un ZIP contenant `word/document.xml`), zéro dépendance |
| `.pdf` | `PdfExtractor` - `smalot/pdfparser` (couche texte uniquement) |
| `.txt` | paragraphes → `<p>` |
| `.html` | tel quel, puis assaini |

L'extracteur Word reconnaît les titres en anglais **et en français**
(`Heading2` comme `Titre2`), le gras/italique/souligné et les tableaux.

Un **PDF scanné** (images sans texte) est refusé avec un message explicite
plutôt qu'importé vide. Le texte d'un PDF perd sa mise en forme (titres,
gras) : seuls les paragraphes sont conservés.

### Performance - pourquoi l'IA ne réécrit pas le document

L'IA **ne renvoie jamais le contrat réécrit**, seulement une courte liste de
fragments à remplacer ; c'est le serveur qui applique les remplacements.

| | Avant (réécriture complète) | Après (remplacements) |
|---|---|---|
| Document de ~30 000 caractères | **31 s** et **échec** : la réponse dépassait la limite de sortie (JSON tronqué) → repli sans variables | **~4 s**, IA effectivement utilisée |
| Latence | proportionnelle à la longueur | quasi indépendante de la longueur |
| Texte du contrat | réécrit par le modèle (risque d'omission/invention) | **jamais modifié** hors des variables |

Mesuré sous les limites PHP par défaut (`max_execution_time=30`,
`memory_limit=128M`). Le temps est dominé par l'appel IA, pas par l'extraction
(docx 0,02 s, pdf 0,7 s pour 30 000 caractères).

Garde-fous de durée :
- le texte envoyé à l'IA est **plafonné à 40 000 caractères** (coupé en fin de
  paragraphe) ; au-delà, le document est importé en entier mais un
  avertissement indique que seule la première partie a été analysée ;
- sortie IA plafonnée à 4 096 tokens, timeout IA de 60 s ;
- le contrôleur relève `max_execution_time` à 120 s et `memory_limit` à 512 Mo
  **pour cette seule requête** (un long PDF dépasse sinon 128 Mo).

### Rôle de l'IA - et ses limites
L'IA reçoit le **texte brut** du modèle et le catalogue des variables
automatiques de la cible, et répond en JSON strict :

```json
{"replacements": [{"find": "350 000", "key": "montant_mensuel"},
                  {"find": "5", "key": "jour_paiement", "context": "avant le 5 de chaque mois"}],
 "manual_variables": [{"key": "montant_mensuel", "label": "Montant mensuel"}]}
```

Règles d'application côté serveur :
- `find` désigne **uniquement la valeur** (jamais l'article ou l'unité voisine) ;
- un `find` d'au moins 3 caractères est remplacé **partout** ; plus court, il
  est **ignoré** sauf s'il porte un `context` ;
- avec `context`, la valeur n'est remplacée **que dans ce passage** - c'est ce
  qui distingue le « 5 » de « avant le 5 de chaque mois » du « 5 » de
  « Article 5 », ou deux « Ouagadougou » de sens différents ;
- **frontières de mot** : `250` ne touche jamais `2500` ni `2026` ;
- espaces tolérants (les PDF produisent des espaces insécables) ;
- fragment le plus long d'abord (`Jean-Baptiste OUEDRAOGO` avant `OUEDRAOGO`) ;
- un `{{variable}}` déjà inséré n'est jamais ré-analysé.

Autres garde-fous :
- une clé devient variable manuelle **seulement si elle a réellement remplacé
  du texte** et n'est pas déjà une variable automatique ;
- un fragment que l'IA a mal recopié, ou à cheval sur deux styles de mise en
  forme, n'est pas remplacé en silence : il est **listé dans l'avertissement** ;
- le parsing JSON reste tolérant (bloc ```json, phrase parasite).

**Repli** : sans clé API, ou si la réponse est inexploitable, le document est
importé **tel quel** avec un avertissement - l'upload n'est jamais une impasse.

### Sécurité - assainissement obligatoire
Le HTML importé est rendu en `{!! !!}` (PDF) et `v-html` (aperçu). Il est donc
passé par un assainisseur DOM qui **n'autorise qu'une liste blanche de balises**
(`p, br, strong, em, u, h1-h4, ul, ol, li, table, tr, td, th, blockquote`),
**supprime tous les attributs** et **supprime entièrement**
`script, style, iframe, object, embed, form, svg`. Le texte des balises non
autorisées est conservé (elles sont « dépliées », pas supprimées).

Testé : `onclick`, `onerror`, `javascript:`, `<script>`, `<iframe>`, `style=`
sont tous neutralisés.

## 12. Organisation dans l'interface

CME n'a **pas** d'onglet propre dans la sidebar : un seul onglet **Contrats**,
depuis lequel on accède, via des **sous-onglets** (`ContractsTabs.vue`), aux contrats, aux CME, aux modèles et à la bibliothèque d'en-têtes & pieds de page.
C'est pourquoi les routes CME vivent sous `/contracts/cmes` et les pages sous
`Pages/Contracts/Cmes/` (le préfixe `Contracts/` garde l'onglet actif).

⚠️ Les routes CME et `contracts/layouts` sont déclarées **avant** `Route::resource('contracts')`,
sinon `contracts/{contract}` capturerait `/contracts/cmes`.

## 13. Reste à faire

- **Versionnage effectif** : `contracts.version` existe et le PDF est archivé
  par version (`contracts/{id}/v{n}.pdf`), mais créer une nouvelle version
  depuis un contrat finalisé n'est pas encore exposé dans l'interface.
- **Cycle de vie non strict** : on peut aujourd'hui rattacher un scan signé à
  un contrat encore en brouillon, ce qui court-circuite la finalisation et
  laisse le contrat sans PDF archivé. À arbitrer.
- Documents > 40 000 caractères : seule la première partie est analysée par l'IA
  (découpage en plusieurs appels non implémenté).
- PDF : pas de reconstitution des titres/gras, et pas d'OCR pour les PDF scannés.
- Assistant IA de rédaction / reformulation de clauses.
