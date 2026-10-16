<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractTarget;
use App\Models\AppSetting;
use App\Models\Letterhead;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Writing a contract: import, AI drafting with progress, recipient identity, letterheads.
 */
class ContractAuthoringTest extends TestCase
{
    use ContractTestHelpers;

    public function test_an_imported_word_file_is_extracted_with_article_headings(): void
    {
        $docx = $this->docx(['ARTICLE 1 : OBJET', 'Le présent contrat a pour objet la formation.']);

        $this->actingAsSuperAdmin()
            ->postJson('/contract-templates/import', [
                'model_file' => new UploadedFile($docx, 'contrat.docx', null, null, true),
                'target' => ContractTarget::Trainer->value,
            ])
            ->assertOk()
            // Line breaks between blocks do not matter: compare the blocks themselves.
            ->assertJson(fn ($json) => $json->where('body_html', fn (string $html) => str_replace("\n", '', $html)
                === '<h2>ARTICLE 1 : OBJET</h2><p>Le présent contrat a pour objet la formation.</p>')->etc());
    }

    public function test_an_unsupported_file_is_refused(): void
    {
        $this->actingAsSuperAdmin()
            ->postJson('/contract-templates/import', [
                'model_file' => UploadedFile::fake()->create('contrat.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'target' => ContractTarget::Trainer->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('model_file');
    }

    public function test_drafting_streams_progress_then_the_cleaned_text(): void
    {
        AppSetting::set('ai_api_key', 'test-key');
        AppSetting::set('ai_provider', 'deepseek');

        $answer = json_encode([
            'body_html' => '<h2>Article 1 : Objet</h2><p>Contrat conclu avec Jean Kaboré.</p><p></p>',
            'signatories' => ['left_title' => 'Pour SIMPLON BURKINA FASO', 'left_name' => 'M. BAGRE Dominique', 'right_title' => 'Pour le Prestataire', 'right_name' => '<b>Jean Kaboré</b>'],
        ]);
        $chunks = str_split($answer, 20);
        $sse = implode('', array_map(fn (string $c) => 'data: '.json_encode(['choices' => [['delta' => ['content' => $c]]]])."\n\n", $chunks))."data: [DONE]\n\n";

        Http::fake(['api.deepseek.com/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->actingAsSuperAdmin()->post('/contract-templates/draft', [
            'action' => 'draft',
            'target' => ContractTarget::Trainer->value,
            'prompt' => 'Contrat de prestation de trois mois.',
        ]);

        $response->assertOk();
        $events = array_map('json_decode', array_filter(explode("\n", $response->streamedContent())));
        $types = array_column($events, 'type');

        $this->assertContains('progress', $types);
        $this->assertSame('done', end($types));
        // Cleaned: the empty paragraph is gone.
        $this->assertSame('<h2>Article 1 : Objet</h2><p>Contrat conclu avec Jean Kaboré.</p>', end($events)->body_html);
        // The signature block comes apart from the text, as plain strings.
        $this->assertSame('Jean Kaboré', end($events)->signatories->right_name);

        // The house rules reach the AI: Simplon's identity and the article format.
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'BF-OUA-01-2023-B17-06061')
            && str_contains($request['messages'][0]['content'], '« Article 1 : Engagement »'));
    }

    public function test_without_ai_key_drafting_reports_an_error_event(): void
    {
        AppSetting::set('ai_api_key', '');

        $response = $this->actingAsSuperAdmin()->post('/contract-templates/draft', [
            'action' => 'draft',
            'target' => ContractTarget::Trainer->value,
            'prompt' => 'Contrat de prestation de trois mois.',
        ]);

        $event = json_decode(trim($response->streamedContent()));
        $this->assertSame('error', $event->type);
    }

    public function test_the_ai_is_told_to_ask_for_a_missing_identity(): void
    {
        AppSetting::set('ai_api_key', 'test-key');
        AppSetting::set('ai_provider', 'deepseek');

        Http::fake(['api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['questions' => ['Quelle est l\'identité complète du formateur ?']])]]],
        ])]);

        $this->actingAsSuperAdmin()
            ->postJson('/contract-templates/draft', [
                'action' => 'questions',
                'target' => ContractTarget::Trainer->value,
                'prompt' => 'Contrat de prestation de trois mois.',
            ])
            ->assertOk()
            ->assertJsonPath('questions.0', 'Quelle est l\'identité complète du formateur ?');

        // No recipient given: the system prompt asks the AI to request the identity, document included.
        Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'si son identité n\'est ni dans la demande')
            && str_contains($request['messages'][0]['content'], 'date et lieu d\'établissement, date d\'expiration'));
    }

    public function test_a_letterhead_is_created_from_uploaded_images_fitted_to_a4(): void
    {
        Storage::fake('public');

        $this->actingAsSuperAdmin()->post('/contracts/layouts', [
            'name' => 'Contrat de prestation',
            // 2100 × 300 px → 210 mm wide, 30 mm high.
            'header_image' => UploadedFile::fake()->image('entete.png', 2100, 300),
        ])->assertRedirect();

        $letterhead = Letterhead::firstOrFail();
        $this->assertSame(30.0, $letterhead->header_height_mm);
        $this->assertNull($letterhead->footer_image_path);
        Storage::disk('public')->assertExists($letterhead->header_image_path);
    }

    public function test_a_letterhead_needs_at_least_one_image(): void
    {
        $this->actingAsSuperAdmin()
            ->post('/contracts/layouts', ['name' => 'Vide'])
            ->assertSessionHasErrors(['header_image', 'footer_image']);
    }

    public function test_a_trainer_cannot_manage_letterheads(): void
    {
        $this->actingAsTrainer()->get('/contracts/layouts')->assertForbidden();
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function docx(array $paragraphs): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        $body = implode('', array_map(fn (string $p) => '<w:p><w:r><w:t>'.htmlspecialchars($p).'</w:t></w:r></w:p>', $paragraphs));

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return $path;
    }
}
