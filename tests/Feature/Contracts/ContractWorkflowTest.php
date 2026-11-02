<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Enums\LetterheadScope;
use App\Jobs\ExtractContractFields;
use App\Models\Contract;
use App\Services\Contracts\ContractVariableResolver;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class ContractWorkflowTest extends TestCase
{
    use ContractTestHelpers;

    public function test_a_contract_is_saved_for_a_recipient_with_a_clean_body(): void
    {
        Queue::fake();
        $trainer = $this->trainer();

        $this->actingAsSuperAdmin()
            ->post('/contracts', [
                'target' => ContractTarget::Trainer->value,
                'subject_id' => $trainer->id,
                'name' => 'Contrat de prestation',
                'body_html' => '<p onclick="x()">Texte<script>alert(1)</script></p><p></p><p><br></p>',
            ])
            ->assertRedirect();

        $contract = Contract::firstOrFail();

        $this->assertSame($trainer->id, $contract->subject_id);
        $this->assertSame('Contrat de prestation', $contract->title);
        $this->assertSame('<p>Texte</p>', $contract->body_html);
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertSame(FillFieldsStatus::Pending, $contract->fill_fields_status);
        // The Excel model is prepared in the background right away.
        Queue::assertPushed(ExtractContractFields::class);
    }

    public function test_the_recipient_is_optional(): void
    {
        Queue::fake();

        $this->actingAsSuperAdmin()
            ->post('/contracts', [
                'target' => ContractTarget::PartnerConvention->value,
                'name' => 'Convention',
                'body_html' => '<p>Convention sans partenaire enregistré.</p>',
            ])
            ->assertRedirect();

        $this->assertNull(Contract::firstOrFail()->subject_id);
    }

    public function test_the_linked_letterhead_and_its_scope_are_frozen_on_the_contract(): void
    {
        Queue::fake();
        $letterhead = $this->letterhead();

        $this->actingAsSuperAdmin()->post('/contracts', [
            'target' => ContractTarget::Trainer->value,
            'name' => 'Contrat',
            'body_html' => '<p>Texte</p>',
            'letterhead_id' => $letterhead->id,
            'letterhead_scope' => LetterheadScope::FirstPage->value,
        ])->assertRedirect();

        $contract = Contract::firstOrFail();
        $this->assertSame(30.0, $contract->header_height_mm);
        $this->assertSame(15.0, $contract->footer_height_mm);
        $this->assertSame(LetterheadScope::FirstPage, $contract->letterhead_scope);
    }

    public function test_a_finalized_contract_stays_editable_and_its_archive_follows(): void
    {
        Queue::fake();
        Storage::fake('local');
        $contract = $this->contract();
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post("/contracts/{$contract->id}/finalize")->assertRedirect();
        $contract->refresh();
        $this->assertSame(ContractStatus::Finalized, $contract->status);
        Storage::disk('local')->assertExists($contract->pdf_path);

        $this->actingAs($user)->get("/contracts/{$contract->id}/edit")->assertOk();
        $this->actingAs($user)
            ->put("/contracts/{$contract->id}", ['title' => 'Contrat corrigé', 'body_html' => '<p>Texte corrigé</p>'])
            ->assertRedirect("/contracts/{$contract->id}");

        $contract->refresh();
        $this->assertSame('<p>Texte corrigé</p>', $contract->body_html);
        // The text changed: its Excel model is detected again.
        Queue::assertPushed(ExtractContractFields::class);
    }

    public function test_the_signature_block_is_saved_and_printed_on_two_columns(): void
    {
        Queue::fake();
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/contracts', [
            'target' => ContractTarget::Trainer->value,
            'name' => 'Contrat de prestation',
            'body_html' => '<p>Texte.</p>',
            'signatories' => ['left_title' => 'Pour SIMPLON BURKINA FASO', 'left_name' => 'M. BAGRE Dominique', 'right_title' => 'Pour le Prestataire', 'right_name' => 'Mme SONGNE'],
        ])->assertRedirect();

        $contract = Contract::firstOrFail();
        $this->assertSame('Mme SONGNE', $contract->signatories['right_name']);

        $text = (new Parser)->parseContent($this->actingAs($user)->get("/contracts/{$contract->id}/pdf")->getContent())->getText();
        $this->assertStringContainsString('Pour SIMPLON BURKINA FASO', $text);
        $this->assertStringContainsString('Mme SONGNE', $text);
    }

    public function test_the_pdf_is_served_with_its_size_and_reused_from_cache(): void
    {
        $contract = $this->contract();
        $user = $this->createSuperAdmin();

        $first = $this->actingAs($user)->get("/contracts/{$contract->id}/pdf/download");
        $first->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame((string) strlen($first->getContent()), $first->headers->get('Content-Length'));

        $second = $this->actingAs($user)->get("/contracts/{$contract->id}/pdf");
        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function test_the_contract_pages_render(): void
    {
        $contract = $this->contract();
        $user = $this->createSuperAdmin();

        foreach (['/contracts', '/contracts/create', "/contracts/{$contract->id}", "/contracts/{$contract->id}/edit", '/contracts/projects', '/contracts/layouts'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_the_list_can_be_searched_by_reference_title_or_recipient(): void
    {
        $trainer = $this->trainer();
        $this->contract(['subject_type' => $trainer->getMorphClass(), 'subject_id' => $trainer->id, 'title' => 'Prestation']);
        $this->contract(['title' => 'Convention de stage']);

        $this->assertSame(1, Contract::search('Traoré')->count());
        $this->assertSame(1, Contract::search('stage')->count());
        $this->assertSame(2, Contract::search('CTR-TEST')->count());
        $this->assertSame(0, Contract::search('introuvable')->count());
    }

    public function test_the_list_is_paginated_with_a_chosen_page_size(): void
    {
        foreach (range(1, 12) as $i) {
            $this->contract(['title' => "Contrat {$i}"]);
        }
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->get('/contracts?per_page=10')
            ->assertInertia(fn ($page) => $page
                ->where('contracts.per_page', 10)
                ->where('contracts.total', 12)
                ->count('contracts.data', 10));

        // A size that is not offered falls back to 20.
        $this->actingAs($user)->get('/contracts?per_page=999')
            ->assertInertia(fn ($page) => $page->where('contracts.per_page', 20)->count('contracts.data', 12));
    }

    public function test_a_trainer_cannot_reach_contracts(): void
    {
        $this->actingAsTrainer()->get('/contracts')->assertForbidden();
        $this->actingAsTrainer()->get('/contracts/projects')->assertForbidden();
    }

    public function test_dates_written_in_contracts_are_in_french(): void
    {
        $trainer = $this->trainer(trainer: ['birth_date' => '1990-03-04']);

        $values = app(ContractVariableResolver::class)->resolve(ContractTarget::Trainer, $trainer);

        $this->assertSame('04 mars 1990', $values['trainer.birth_date']);
    }
}
