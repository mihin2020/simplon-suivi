<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Identity documents, CVs, contract scans and receipts are private: opened only through a checked route. */
class PrivateFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function learnerWithDocuments(): Learner
    {
        Storage::disk('local')->put('learners/cnib/id.pdf', '%PDF cnib');
        Storage::disk('local')->put('learners/cv/cv.pdf', '%PDF cv');

        return Learner::factory()->create([
            'cnib_path' => 'learners/cnib/id.pdf', 'cnib_original_name' => 'ma-cnib.pdf',
            'cv_path' => 'learners/cv/cv.pdf', 'cv_original_name' => 'mon-cv.pdf',
        ]);
    }

    private function actingWithPermission(string $slug): void
    {
        $user = $this->createAdmin();
        $user->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'T'])->id);
        $this->actingAs($user);
    }

    // ── Learner documents ──────────────────────────────────────────────────

    public function test_an_authorised_user_opens_a_learner_document_with_its_original_name(): void
    {
        $learner = $this->learnerWithDocuments();
        $this->actingWithPermission('learners.view');

        $cnib = $this->get(route('learners.documents.show', [$learner, 'cnib']))->assertOk();
        $this->assertStringContainsString('inline', $cnib->headers->get('Content-Disposition'));
        $this->assertStringContainsString('ma-cnib.pdf', $cnib->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $cnib->headers->get('Cache-Control'));
        $this->assertSame('%PDF cnib', $cnib->streamedContent());

        $this->get(route('learners.documents.show', [$learner, 'cv']))->assertOk();
    }

    public function test_a_learner_document_is_refused_without_permission_and_to_guests(): void
    {
        $learner = $this->learnerWithDocuments();

        $this->get(route('learners.documents.show', [$learner, 'cnib']))->assertRedirect(route('login'));

        $this->actingAs($this->createAdmin())->get(route('learners.documents.show', [$learner, 'cnib']))->assertForbidden();
        $this->actingAs($this->createTrainer())->get(route('learners.documents.show', [$learner, 'cv']))->assertForbidden();
    }

    public function test_an_unknown_document_kind_or_a_missing_file_is_a_404(): void
    {
        $learner = $this->learnerWithDocuments();
        $this->actingAsSuperAdmin();

        $this->get('/learners/'.$learner->id.'/documents/passport')->assertNotFound();
        $this->get('/learners/'.$learner->id.'/documents/../../.env')->assertNotFound();

        $learner->update(['cnib_path' => null]);
        $this->get(route('learners.documents.show', [$learner, 'cnib']))->assertNotFound();

        $learner->update(['cnib_path' => 'learners/cnib/vanished.pdf']);
        $this->get(route('learners.documents.show', [$learner, 'cnib']))->assertNotFound();
    }

    public function test_the_path_stored_in_the_database_cannot_escape_the_disk(): void
    {
        $learner = $this->learnerWithDocuments();
        $learner->update(['cnib_path' => '../../../.env']);
        $this->actingAsSuperAdmin();

        $this->get(route('learners.documents.show', [$learner, 'cnib']))->assertNotFound();
    }

    // ── Insertion contracts ────────────────────────────────────────────────

    public function test_a_contract_scan_is_served_to_those_who_can_view_the_learner_only(): void
    {
        $learner = Learner::factory()->create();
        $other = Learner::factory()->create();
        Storage::disk('local')->put('insertion-records/internship/scan.pdf', '%PDF scan');
        $record = InsertionRecord::create([
            'learner_id' => $learner->id, 'status' => 'internship', 'status_changed_at' => now(),
            'internship_contract_path' => 'insertion-records/internship/scan.pdf', 'internship_contract_original_name' => 'convention.pdf',
        ]);

        $this->actingAs($this->createAdmin())->get(route('learners.insertion.contract', [$learner, $record, 'internship']))->assertForbidden();

        $this->actingWithPermission('learners.view');
        $this->get(route('learners.insertion.contract', [$learner, $record, 'internship']))->assertOk();
        $this->get(route('learners.insertion.contract', [$learner, $record, 'employment']))->assertNotFound();
        $this->get(route('learners.insertion.contract', [$other, $record, 'internship']))->assertNotFound();
    }

    // ── Trainer CV ─────────────────────────────────────────────────────────

    public function test_a_trainer_cv_is_opened_by_its_owner_and_by_staff_but_not_by_another_trainer(): void
    {
        Storage::disk('local')->put('trainers/cvs/cv.pdf', '%PDF trainer');
        $owner = $this->createTrainer();
        $trainer = Trainer::create(['user_id' => $owner->id, 'is_active' => true, 'cv_path' => 'trainers/cvs/cv.pdf']);

        $this->actingAs($owner)->get(route('trainers.cv', $trainer))->assertOk();
        $this->actingAs($this->createTrainer())->get(route('trainers.cv', $trainer))->assertForbidden();
        $this->actingAs($this->createAdmin())->get(route('trainers.cv', $trainer))->assertForbidden();
        $this->actingAsSuperAdmin()->get(route('trainers.cv', $trainer))->assertOk();
    }

    // ── Expense receipts ───────────────────────────────────────────────────

    public function test_an_expense_receipt_needs_the_expenses_permission(): void
    {
        $formation = Formation::factory()->create(['project_id' => Project::factory()->create()->id]);
        $expense = Expense::create(['formation_id' => $formation->id, 'title' => 'Transport', 'amount' => 1000, 'expense_date' => now(), 'spent_by' => 'Awa']);
        Storage::disk('local')->put('expenses/receipt.pdf', '%PDF receipt');
        $attachment = ExpenseAttachment::create(['expense_id' => $expense->id, 'file_path' => 'expenses/receipt.pdf', 'original_name' => 'recu.pdf', 'mime_type' => 'application/pdf', 'size' => 12]);

        $this->get(route('expenses.attachments.show', $attachment))->assertRedirect(route('login'));
        $this->actingAs($this->createAdmin())->get(route('expenses.attachments.show', $attachment))->assertForbidden();

        $this->actingWithPermission('expenses.view');
        $this->get(route('expenses.attachments.show', $attachment))->assertOk();
    }

    // ── Moving the files already stored ────────────────────────────────────

    public function test_existing_public_files_are_moved_to_the_private_disk_and_photos_stay_public(): void
    {
        foreach (['learners/cnib/a.pdf', 'learners/cv/b.pdf', 'trainers/cvs/c.pdf', 'contracts/1/v1.pdf', 'expenses/1/d.pdf', 'insertion-records/employment/e.pdf'] as $path) {
            Storage::disk('public')->put($path, 'content of '.$path);
        }
        Storage::disk('public')->put('learners/photos/p.jpg', 'photo');
        Storage::disk('public')->put('partners/logos/l.png', 'logo');

        $this->artisan('files:make-private --dry-run')->assertSuccessful();
        Storage::disk('public')->assertExists('learners/cnib/a.pdf');
        Storage::disk('local')->assertMissing('learners/cnib/a.pdf');

        $this->artisan('files:make-private')->expectsOutput('Moved: 6')->assertSuccessful();

        foreach (['learners/cnib/a.pdf', 'learners/cv/b.pdf', 'trainers/cvs/c.pdf', 'contracts/1/v1.pdf', 'expenses/1/d.pdf', 'insertion-records/employment/e.pdf'] as $path) {
            Storage::disk('public')->assertMissing($path);
            $this->assertSame('content of '.$path, Storage::disk('local')->get($path));
        }

        Storage::disk('public')->assertExists('learners/photos/p.jpg');
        Storage::disk('public')->assertExists('partners/logos/l.png');

        $this->artisan('files:make-private')->expectsOutput('Moved: 0')->assertSuccessful();
    }
}
