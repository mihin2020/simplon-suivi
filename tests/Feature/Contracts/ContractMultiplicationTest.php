<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractEmailStatus;
use App\Enums\ContractStatus;
use App\Enums\ContractTarget;
use App\Enums\FillFieldsStatus;
use App\Jobs\SendContractsByEmail;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\Notification;
use App\Models\User;
use App\Services\Contracts\ContractBulkService;
use App\Services\Contracts\ContractFieldExtractor;
use App\Services\Contracts\ContractSubjectDirectory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use ZipArchive;

/**
 * One contract, many people: duplication, Excel model, projects and grouped actions.
 */
class ContractMultiplicationTest extends TestCase
{
    use ContractTestHelpers;

    public function test_duplicating_rewrites_the_identity_for_each_new_recipient(): void
    {
        $awa = $this->trainer();
        $jean = $this->trainer(['first_name' => 'Jean', 'last_name' => 'Kaboré', 'email' => 'jean@example.test'], ['address' => '', 'id_number' => 'B7654321']);
        $source = $this->contract([
            'subject_type' => $awa->getMorphClass(),
            'subject_id' => $awa->id,
            'body_html' => '<p>Awa Traoré, domiciliée à Secteur 15, Ouagadougou, pièce B1234567.</p>',
        ]);

        $this->actingAsSuperAdmin()
            ->post("/contracts/{$source->id}/duplicate", ['subject_ids' => [$jean->id]])
            ->assertRedirect();

        $copy = Contract::where('subject_id', $jean->id)->firstOrFail();
        $this->assertSame('<p>Jean Kaboré, domiciliée à ………………, pièce B7654321.</p>', $copy->body_html);
        $this->assertNotSame($source->reference, $copy->reference);
    }

    public function test_platform_users_can_be_picked_as_recipients(): void
    {
        $user = User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Zongo', 'is_active' => true]);
        $source = $this->contract(['target' => ContractTarget::Cme]);

        $this->actingAsSuperAdmin()
            ->post("/contracts/{$source->id}/duplicate", ['subject_ids' => [$user->id]])
            ->assertRedirect();

        $this->assertSame(1, Contract::where('subject_id', $user->id)->count());
    }

    public function test_the_excel_model_lists_the_person_specific_passages(): void
    {
        $awa = $this->trainer();
        $contract = $this->contract([
            'subject_type' => $awa->getMorphClass(),
            'subject_id' => $awa->id,
            'body_html' => '<p>Contrat avec Awa Traoré, pièce B1234567, domiciliée à Secteur 15, Ouagadougou.</p>',
        ]);

        // No AI key in tests: the recipient's known identity is still found.
        app(ContractFieldExtractor::class)->refresh($contract);
        $contract->refresh();

        $this->assertSame(FillFieldsStatus::Ready, $contract->fill_fields_status);
        $values = array_column($contract->fill_fields, 'value');
        $this->assertContains('Awa Traoré', $values);
        $this->assertContains('B1234567', $values);

        Excel::fake();
        $this->actingAsSuperAdmin()->get("/contracts/{$contract->id}/fill-model")->assertOk();
        Excel::assertDownloaded('modele_contrat-de-prestation.xlsx');
    }

    public function test_a_filled_excel_model_generates_one_contract_per_row(): void
    {
        $source = $this->contract([
            'body_html' => '<p>Contrat avec Awa Traoré pour 250 000 francs CFA.</p>',
            'fill_fields' => [
                ['key' => 'name', 'label' => 'Nom complet', 'value' => 'Awa Traoré'],
                ['key' => 'ai_1', 'label' => 'Montant', 'value' => '250 000'],
            ],
            'fill_fields_status' => FillFieldsStatus::Ready,
        ]);

        $file = $this->csv([
            ['Nom complet', 'Montant'],
            ['Awa Traoré', '250 000'],   // the example row: skipped
            ['Jean Kaboré', '300 000'],
            ['Marie Zongo', ''],          // empty cell: original value kept
            ['', ''],                     // empty row: skipped
        ]);

        $this->actingAsSuperAdmin()
            ->post("/contracts/{$source->id}/fill-model", ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = Contract::whereKeyNot($source->id)->orderBy('title')->get();
        $this->assertCount(2, $created);
        $this->assertSame('<p>Contrat avec Jean Kaboré pour 300 000 francs CFA.</p>', $created[0]->body_html);
        $this->assertSame('Contrat de prestation : Jean Kaboré', $created[0]->title);
        $this->assertSame('<p>Contrat avec Marie Zongo pour 250 000 francs CFA.</p>', $created[1]->body_html);
    }

    public function test_an_excel_file_with_other_columns_is_refused(): void
    {
        $source = $this->contract([
            'fill_fields' => [['key' => 'name', 'label' => 'Nom complet', 'value' => 'Awa Traoré']],
            'fill_fields_status' => FillFieldsStatus::Ready,
        ]);

        $this->actingAsSuperAdmin()
            ->post("/contracts/{$source->id}/fill-model", ['file' => $this->csv([['Autre colonne'], ['Valeur']])])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, Contract::count());
    }

    public function test_a_project_groups_contracts_and_deleting_it_deletes_them(): void
    {
        Queue::fake();
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/contracts/projects', [
            'name' => 'Formateurs cohorte 2026',
            'description' => 'Contrats des formateurs.',
            'target' => ContractTarget::Trainer->value,
        ])->assertRedirect();
        $project = ContractProject::firstOrFail();

        $this->actingAs($user)->post('/contracts', [
            'target' => ContractTarget::Trainer->value,
            'contract_project_id' => $project->id,
            'name' => 'Contrat',
            'body_html' => '<p>Texte</p>',
        ])->assertRedirect("/contracts/projects/{$project->id}");

        $this->actingAs($user)->get("/contracts/projects/{$project->id}")->assertOk();
        $this->assertSame(1, $project->contracts()->count());

        $outside = $this->contract();

        $this->actingAs($user)->delete("/contracts/projects/{$project->id}")
            ->assertRedirect('/contracts/projects')
            ->assertSessionHas('success', 'Projet supprimé, ainsi que son contrat.');

        $this->assertSoftDeleted($project);
        $this->assertSame(0, $project->contracts()->count());
        $this->assertSame(1, $project->contracts()->onlyTrashed()->count());
        $this->assertNotSoftDeleted($outside);
    }

    public function test_a_contract_can_be_copied_identically_then_edited(): void
    {
        Queue::fake();
        $awa = $this->trainer();
        $project = ContractProject::create(['name' => 'Cohorte 2026', 'target' => ContractTarget::Trainer]);
        $source = $this->contract([
            'contract_project_id' => $project->id,
            'subject_type' => $awa->getMorphClass(),
            'subject_id' => $awa->id,
            'status' => ContractStatus::Finalized,
            'version' => 3,
            'pdf_path' => 'contracts/source.pdf',
            'emailed_at' => now(),
            'email_count' => 2,
            'signatories' => ['left_title' => 'Le Directeur', 'left_name' => 'X'],
        ]);

        $response = $this->actingAsSuperAdmin()->post("/contracts/{$source->id}/copy");

        $copy = Contract::whereKeyNot($source->id)->firstOrFail();
        $response->assertRedirect("/contracts/{$copy->id}/edit");

        $this->assertNotSame($source->reference, $copy->reference);
        $this->assertSame($source->body_html, $copy->body_html);
        $this->assertSame($source->title, $copy->title);
        $this->assertSame($source->signatories, $copy->signatories);
        $this->assertSame($awa->id, $copy->subject_id);
        $this->assertSame($project->id, $copy->contract_project_id);
        // A fresh draft: nothing archived or sent yet.
        $this->assertSame(ContractStatus::Draft, $copy->status);
        $this->assertSame(1, $copy->version);
        $this->assertNull($copy->pdf_path);
        $this->assertNull($copy->emailed_at);
        $this->assertSame(0, (int) $copy->email_count);

        // Changing the copy leaves the original alone.
        $this->actingAsSuperAdmin()->put("/contracts/{$copy->id}", ['title' => 'Avenant n°1', 'body_html' => '<p>Nouveau texte</p>'])->assertRedirect();
        $this->assertSame('Contrat de prestation', $source->fresh()->title);
        $this->assertSame('Avenant n°1', $copy->fresh()->title);
    }

    public function test_a_contract_without_recipient_can_be_copied(): void
    {
        $source = $this->contract();

        $this->actingAsSuperAdmin()->post("/contracts/{$source->id}/copy")->assertRedirect();

        $copy = Contract::whereKeyNot($source->id)->firstOrFail();
        $this->assertNull($copy->subject_id);
        $this->assertSame($source->body_html, $copy->body_html);
    }

    public function test_selected_contracts_download_as_one_zip(): void
    {
        $a = $this->contract();
        $b = $this->contract();

        $response = $this->actingAsSuperAdmin()->post('/contracts/bulk/download', ['ids' => [$a->id, $b->id]]);
        $response->assertOk()->assertHeader('Content-Type', 'application/zip');

        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
    }

    public function test_selected_contracts_can_be_deleted(): void
    {
        $a = $this->contract();
        $b = $this->contract();

        $this->actingAsSuperAdmin()->post('/contracts/bulk/delete', ['ids' => [$a->id, $b->id]])->assertRedirect();

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
    }

    public function test_emailing_contracts_is_queued_then_sends_and_notifies(): void
    {
        Queue::fake();
        $awa = $this->trainer();
        $contract = $this->contract(['subject_type' => $awa->getMorphClass(), 'subject_id' => $awa->id]);
        $user = $this->createSuperAdmin();

        $this->actingAs($user)->post('/contracts/bulk/email', [
            'ids' => [$contract->id],
            'mode' => 'each',
            'subject' => 'Votre contrat',
            'message' => 'Bonjour, voici votre contrat.',
        ])->assertRedirect()->assertSessionHas('success');

        // Marked "being sent" in the lists until the worker is done.
        $this->assertSame(ContractEmailStatus::Queued, $contract->fresh()->email_status);

        // The page answers at once: the sending itself is a queued job.
        Queue::assertPushed(SendContractsByEmail::class, function (SendContractsByEmail $job) use ($contract) {
            return $job->contractIds === [$contract->id] && $job->mode === 'each';
        });

        Mail::fake();
        (new SendContractsByEmail([$contract->id], 'each', [], [], 'Votre contrat', 'Bonjour.', $user->id))
            ->handle(app(ContractBulkService::class));

        $this->assertSame(1, Notification::where('user_id', $user->id)->where('type', 'contracts_emailed')->count());

        // The sending is remembered on the contract, so it shows as already sent.
        $contract->refresh();
        $this->assertNotNull($contract->emailed_at);
        $this->assertSame('awa.traore@example.test', $contract->emailed_to);
        $this->assertSame(1, $contract->email_count);
        $this->assertSame(ContractEmailStatus::Sent, $contract->email_status);
        $this->assertSame(1, Contract::emailStatus('sent')->count());
        $this->assertSame(0, Contract::emailStatus('none')->count());
    }

    public function test_an_address_typed_when_sending_is_kept_on_the_contract(): void
    {
        Queue::fake();
        $contract = $this->contract();

        $this->assertFalse(app(ContractSubjectDirectory::class)->isNominative($contract));

        $this->actingAsSuperAdmin()->post('/contracts/bulk/email', [
            'ids' => [$contract->id],
            'mode' => 'each',
            'recipients' => [$contract->id => 'jean.kabore@example.test'],
            'subject' => 'Votre contrat',
            'message' => 'Bonjour.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('jean.kabore@example.test', $contract->recipient_email);
        // From now on the contract is known as nominative, with its address.
        $this->assertSame('jean.kabore@example.test', app(ContractSubjectDirectory::class)->contractEmail($contract));
        Queue::assertPushed(SendContractsByEmail::class);
    }

    public function test_the_contract_address_beats_the_recipient_record(): void
    {
        $awa = $this->trainer();
        $contract = $this->contract(['subject_type' => $awa->getMorphClass(), 'subject_id' => $awa->id]);
        $directory = app(ContractSubjectDirectory::class);

        $this->assertSame('awa.traore@example.test', $directory->contractEmail($contract->load('subject')));

        $contract->update(['recipient_email' => 'awa.pro@example.test']);
        $this->assertSame('awa.pro@example.test', $directory->contractEmail($contract->fresh('subject')));
    }

    public function test_the_excel_email_column_gives_each_generated_contract_its_address(): void
    {
        $source = $this->contract([
            'body_html' => '<p>Contrat avec Awa Traoré.</p>',
            'fill_fields' => [['key' => 'name', 'label' => 'Nom complet', 'value' => 'Awa Traoré']],
            'fill_fields_status' => FillFieldsStatus::Ready,
        ]);

        $this->actingAsSuperAdmin()->post("/contracts/{$source->id}/fill-model", ['file' => $this->csv([
            ['Nom complet', 'Email du destinataire'],
            ['Jean Kaboré', 'jean@example.test'],
            ['Marie Zongo', 'adresse-invalide'],
        ])])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('jean@example.test', Contract::where('title', 'like', '%Jean Kaboré')->value('recipient_email'));
        $this->assertNull(Contract::where('title', 'like', '%Marie Zongo')->value('recipient_email'));
    }

    public function test_a_failed_sending_is_flagged_so_it_can_be_sent_again(): void
    {
        $contract = $this->contract(['email_status' => ContractEmailStatus::Queued]);
        $user = $this->createSuperAdmin();

        (new SendContractsByEmail([$contract->id], 'each', [], [], 'Objet', 'Texte', $user->id))
            ->failed(new \RuntimeException('SMTP indisponible'));

        $this->assertSame(ContractEmailStatus::Failed, $contract->fresh()->email_status);
        $this->assertSame(1, Contract::emailStatus('failed')->count());
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'model').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'modele.csv', 'text/csv', null, true);
    }
}
