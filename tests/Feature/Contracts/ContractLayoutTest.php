<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractFont;
use App\Enums\ContractLineSpacing;
use App\Enums\ContractTarget;
use App\Enums\LetterheadScope;
use App\Services\Contracts\ContractTextStructurer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Layout of a contract: structure rebuilt on import, letterhead and font
 * chosen when writing and changed when editing.
 */
class ContractLayoutTest extends TestCase
{
    use ContractTestHelpers;

    /** Text as a PDF hands it over: one line per visual line, 70 characters wide. */
    private const PDF_TEXT = <<<'TXT'
CONTRAT DE PRESTATION DE SERVICES

ENTRE LES SOUSSIGNÉS :
L'organisme de formation, société par actions simplifiée,immatriculée au
registre du commerce, représenté par son directeur, dûment habilité.
Ci-après nommé « LE CLIENT »
Article 1 : Objet du contrat
Le présent contrat confie au Prestataire, qui l'accepte, une mission de
coordination pédagogique au sein de la fabrique.
Article 2 : Missions
2.1. Sourcing et relations partenaires
●​Nouer et entretenir des relations avec les prescripteurs de la formation
et les acteurs de l'emploi.
●​Coordonner le recrutement des apprenants.
Article 3 : Durée
Le contrat prend effet à compter du 1
er
juin 2026.
Page 1 sur 2
Fait à Ouagadougou, le 2 juin 2026

Pour LE CLIENT                                        Pour le Prestataire

M. Jean OUEDRAOGO                                     Mme Aline ZONGO
TXT;

    public function test_a_text_extracted_from_a_pdf_gets_its_structure_back(): void
    {
        $document = app(ContractTextStructurer::class)->structure(self::PDF_TEXT);
        $html = $document['body_html'];

        // The title and the signatures are printed apart by the PDF.
        $this->assertSame('CONTRAT DE PRESTATION DE SERVICES', $document['title']);
        $this->assertSame([
            'left_title' => 'Pour LE CLIENT',
            'right_title' => 'Pour le Prestataire',
            'left_name' => 'M. Jean OUEDRAOGO',
            'right_name' => 'Mme Aline ZONGO',
        ], $document['signatories']);
        $this->assertStringNotContainsString('CONTRAT DE PRESTATION', $html);

        // Lines cut by the page width are one paragraph again.
        $this->assertStringContainsString('<p>Le présent contrat confie au Prestataire, qui l&#039;accepte, une mission de coordination pédagogique au sein de la fabrique.</p>', $html);
        $this->assertStringContainsString('simplifiée, immatriculée', $html);
        $this->assertStringContainsString('<p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>', $html);
        $this->assertStringContainsString('<h2>Article 1 : Objet du contrat</h2>', $html);
        $this->assertStringContainsString('<h3>2.1. Sourcing et relations partenaires</h3>', $html);
        $this->assertStringContainsString('<ul><li>Nouer et entretenir des relations avec les prescripteurs de la formation et les acteurs de l&#039;emploi.</li><li>Coordonner le recrutement des apprenants.</li></ul>', $html);
        $this->assertStringContainsString('à compter du 1er juin 2026.', $html);
        $this->assertStringNotContainsString('Page 1 sur 2', $html);
        $this->assertStringContainsString('<p>Fait à Ouagadougou, le 2 juin 2026</p>', $html);
    }

    public function test_an_imported_text_file_returns_its_title_and_signatures(): void
    {
        $file = UploadedFile::fake()->createWithContent('contrat.txt', self::PDF_TEXT);

        $this->actingAsSuperAdmin()
            ->postJson('/contract-templates/import', ['model_file' => $file, 'target' => ContractTarget::Trainer->value])
            ->assertOk()
            ->assertJsonPath('title', 'CONTRAT DE PRESTATION DE SERVICES')
            ->assertJsonPath('signatories.right_name', 'Mme Aline ZONGO');
    }

    public function test_word_lists_are_imported_as_lists(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .'<w:p><w:r><w:t>Tâches :</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Tenue comptable</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>- Gestion de la caisse</w:t></w:r></w:p>'
            .'</w:body></w:document>';
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $this->actingAsSuperAdmin()
            ->postJson('/contract-templates/import', [
                'model_file' => new UploadedFile($path, 'contrat.docx', null, null, true),
                'target' => ContractTarget::Trainer->value,
            ])
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('body_html', fn (string $html) => str_replace("\n", '', $html)
                === '<p>Tâches :</p><ul><li>Tenue comptable</li><li>Gestion de la caisse</li></ul>')->etc());
    }

    public function test_the_letterhead_and_the_font_are_changed_when_editing(): void
    {
        Queue::fake();
        $first = $this->letterhead(['name' => 'Ancien']);
        $second = $this->letterhead(['name' => 'Nouveau', 'header_height_mm' => 40, 'footer_height_mm' => 0]);
        $contract = $this->contract(['letterhead_id' => $first->id, 'header_height_mm' => 30, 'footer_height_mm' => 15, 'letterhead_scope' => LetterheadScope::AllPages]);

        $this->actingAsSuperAdmin()
            ->get("/contracts/{$contract->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contract.letterhead_id', $first->id)
                ->where('contract.font', ContractFont::DejaVuSerif->value)
                ->where('contract.line_spacing', ContractLineSpacing::OneHalf->value)
                ->has('lineSpacings', 4)
                ->has('letterheads', 2)
                ->has('fonts', count(ContractFont::cases())));

        $this->put("/contracts/{$contract->id}", [
            'title' => $contract->title,
            'body_html' => $contract->body_html,
            'letterhead_id' => $second->id,
            'letterhead_scope' => LetterheadScope::FirstPage->value,
            'font' => ContractFont::Poppins->value,
            'line_spacing' => ContractLineSpacing::Double->value,
        ])->assertRedirect();

        $contract->refresh();
        $this->assertSame($second->id, $contract->letterhead_id);
        $this->assertSame(40.0, $contract->header_height_mm);
        $this->assertSame(LetterheadScope::FirstPage, $contract->letterhead_scope);
        $this->assertSame(ContractFont::Poppins, $contract->font);
        $this->assertSame(ContractLineSpacing::Double, $contract->line_spacing);

        // The PDF embeds the chosen font.
        $this->assertStringContainsString('Poppins', $this->get("/contracts/{$contract->id}/pdf")->assertOk()->getContent());

        // No letterhead: the bands go.
        $this->put("/contracts/{$contract->id}", ['title' => $contract->title, 'letterhead_id' => null])->assertRedirect();
        $contract->refresh();
        $this->assertNull($contract->letterhead_id);
        $this->assertNull($contract->header_height_mm);
    }

    public function test_an_unknown_font_is_refused(): void
    {
        $contract = $this->contract();

        $this->actingAsSuperAdmin()
            ->put("/contracts/{$contract->id}", ['title' => $contract->title, 'font' => 'comic-sans'])
            ->assertSessionHasErrors('font');
    }
}
