<?php

namespace Tests\Feature;

use App\Enums\ContractTypeContext;
use App\Enums\InsertionStatus;
use App\Models\ContractType;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pins how the professional insertion history of a learner (searching, internship, job) is recorded. */
class InsertionRecordTest extends TestCase
{
    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 10:00:00');
        Storage::fake('local');

        ContractType::create(['name' => 'Stage académique', 'context' => ContractTypeContext::Internship, 'order' => 1]);
        ContractType::create(['name' => 'CDI', 'context' => ContractTypeContext::Employment, 'order' => 1]);

        $this->learner = Learner::factory()->create();
        $this->actingAsSuperAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function store(array $payload)
    {
        return $this->post(route('learners.insertion.store', $this->learner), $payload);
    }

    private function internship(array $extra = []): array
    {
        return [
            'status' => 'internship', 'internship_start_date' => '2026-11-10', 'internship_company' => 'Acme',
            'internship_contract_type' => 'Stage académique', 'internship_work_mode' => 'presentiel', ...$extra,
        ];
    }

    private function employment(array $extra = []): array
    {
        return [
            'status' => 'employed', 'employment_company' => 'Globex', 'employment_start_date' => '2026-12-01',
            'employment_contract_type' => 'CDI', 'employment_work_mode' => 'en_ligne', 'employment_position' => 'Dev', ...$extra,
        ];
    }

    // ── Recording ──────────────────────────────────────────────────────────

    public function test_a_search_status_needs_nothing_more(): void
    {
        $this->store(['status' => 'searching', 'status_notes' => 'Cherche un stage'])
            ->assertRedirect(route('learners.show', $this->learner))
            ->assertSessionHas('success', "Le statut d'insertion a été enregistré avec succès.");

        $record = InsertionRecord::firstOrFail();
        $this->assertSame($this->learner->id, $record->learner_id);
        $this->assertSame(InsertionStatus::Searching, $record->status);
        $this->assertSame('Cherche un stage', $record->status_notes);
        $this->assertSame('2026-11-02', $record->status_changed_at->toDateString(), 'defaults to now');
        $this->assertSame(auth()->id(), $record->recorded_by);
    }

    public function test_an_internship_records_its_details(): void
    {
        $this->store($this->internship(['internship_paid' => true, 'internship_end_date' => '2027-02-10', 'status_changed_at' => '2026-11-05']))->assertRedirect();

        $record = InsertionRecord::firstOrFail();
        $this->assertSame('Acme', $record->internship_company);
        $this->assertSame('Stage académique', $record->internship_contract_type);
        $this->assertTrue($record->internship_paid);
        $this->assertSame('2026-11-10', $record->internship_start_date->toDateString());
        $this->assertSame('2026-11-05', $record->status_changed_at->toDateString());
    }

    public function test_a_job_records_its_details(): void
    {
        $this->store($this->employment())->assertRedirect();

        $record = InsertionRecord::firstOrFail();
        $this->assertSame(InsertionStatus::Employed, $record->status);
        $this->assertSame(['Globex', 'Dev', 'CDI'], [$record->employment_company, $record->employment_position, $record->employment_contract_type]);
    }

    public function test_the_details_required_by_the_status_are_enforced(): void
    {
        $this->store(['status' => 'internship'])->assertSessionHasErrors(['internship_start_date', 'internship_company', 'internship_contract_type', 'internship_work_mode']);
        $this->store(['status' => 'employed'])->assertSessionHasErrors(['employment_company', 'employment_start_date', 'employment_contract_type', 'employment_work_mode']);
        $this->assertSame(0, InsertionRecord::count());
    }

    public function test_values_are_checked(): void
    {
        $this->store(['status' => 'retired'])->assertSessionHasErrors('status');
        $this->store($this->internship(['internship_contract_type' => 'CDI']))->assertSessionHasErrors('internship_contract_type');
        $this->store($this->employment(['employment_contract_type' => 'Stage académique']))->assertSessionHasErrors('employment_contract_type');
        $this->store($this->internship(['internship_work_mode' => 'sur_mars']))->assertSessionHasErrors('internship_work_mode');
        $this->store($this->internship(['internship_end_date' => '2026-01-01']))->assertSessionHasErrors('internship_end_date');
        $this->store(['status' => 'searching', 'status_notes' => str_repeat('x', 1001)])->assertSessionHasErrors('status_notes');
        $this->assertSame(0, InsertionRecord::count());
    }

    public function test_contract_files_are_stored_with_their_original_name(): void
    {
        $this->store($this->internship(['internship_contract_file' => UploadedFile::fake()->create('convention.pdf', 100, 'application/pdf')]))->assertRedirect();
        $this->store($this->employment(['employment_contract_file' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf')]))->assertRedirect();

        $internship = InsertionRecord::where('status', 'internship')->firstOrFail();
        $employment = InsertionRecord::where('status', 'employed')->firstOrFail();

        $this->assertSame('convention.pdf', $internship->internship_contract_original_name);
        $this->assertStringStartsWith('insertion-records/internship/', $internship->internship_contract_path);
        Storage::disk('local')->assertExists($internship->internship_contract_path);
        $this->assertSame('contrat.pdf', $employment->employment_contract_original_name);
        $this->assertStringStartsWith('insertion-records/employment/', $employment->employment_contract_path);
    }

    public function test_an_unsupported_contract_file_is_refused(): void
    {
        $this->store($this->internship(['internship_contract_file' => UploadedFile::fake()->create('script.exe', 10)]))->assertSessionHasErrors('internship_contract_file');
        $this->store($this->internship(['internship_contract_file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('internship_contract_file');
        $this->assertSame(0, InsertionRecord::count());
    }

    // ── Updating ───────────────────────────────────────────────────────────

    private function record(array $attributes = []): InsertionRecord
    {
        return InsertionRecord::create([
            'learner_id' => $this->learner->id, 'status' => 'internship', 'status_changed_at' => now(), 'recorded_by' => auth()->id(),
            'internship_start_date' => '2026-11-10', 'internship_company' => 'Acme', 'internship_contract_type' => 'Stage académique',
            'internship_work_mode' => 'presentiel', ...$attributes,
        ]);
    }

    private function update(InsertionRecord $record, array $payload)
    {
        return $this->put(route('learners.insertion.update', [$this->learner, $record]), $payload);
    }

    public function test_an_update_rewrites_the_details_and_keeps_the_recording_date(): void
    {
        $record = $this->record();
        $changedAt = $record->status_changed_at;

        $this->update($record, $this->employment())->assertRedirect(route('learners.show', $this->learner))
            ->assertSessionHas('success', "Le statut d'insertion a été mis à jour.");

        $record->refresh();
        $this->assertSame(InsertionStatus::Employed, $record->status);
        $this->assertSame('Globex', $record->employment_company);
        $this->assertNull($record->internship_company, 'details that are not submitted are cleared');
        $this->assertEquals($changedAt, $record->status_changed_at, 'the date of the status is not rewritten');
    }

    public function test_an_update_does_not_require_the_status_details(): void
    {
        $record = $this->record();

        $this->update($record, ['status' => 'internship'])->assertSessionHasNoErrors();

        $this->assertNull($record->fresh()->internship_company);
    }

    public function test_a_contract_file_is_replaced_or_removed(): void
    {
        $this->store($this->internship(['internship_contract_file' => UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf')]));
        $record = InsertionRecord::firstOrFail();
        $first = $record->internship_contract_path;

        $this->update($record, $this->internship(['internship_contract_file' => UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf')]));
        $record->refresh();
        $this->assertSame('v2.pdf', $record->internship_contract_original_name);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($record->internship_contract_path);

        $second = $record->internship_contract_path;
        $this->update($record, $this->internship(['remove_internship_contract' => true]));
        $record->refresh();
        $this->assertNull($record->internship_contract_path);
        $this->assertNull($record->internship_contract_original_name);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_deleting_an_entry_deletes_its_files(): void
    {
        $this->store($this->employment(['employment_contract_file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')]));
        $record = InsertionRecord::firstOrFail();
        $path = $record->employment_contract_path;

        $this->delete(route('learners.insertion.destroy', [$this->learner, $record]))
            ->assertRedirect(route('learners.show', $this->learner))->assertSessionHas('success', "L'entrée a été supprimée.");

        $this->assertNull(InsertionRecord::find($record->id));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_an_entry_of_another_learner_is_not_reachable(): void
    {
        $foreign = InsertionRecord::create(['learner_id' => Learner::factory()->create()->id, 'status' => 'searching', 'status_changed_at' => now(), 'recorded_by' => auth()->id()]);

        $this->update($foreign, ['status' => 'searching'])->assertNotFound();
        $this->delete(route('learners.insertion.destroy', [$this->learner, $foreign]))->assertNotFound();
        $this->assertNotNull(InsertionRecord::find($foreign->id));
    }

    // ── Page and access ────────────────────────────────────────────────────

    public function test_the_page_lists_the_history_newest_first_with_the_statuses(): void
    {
        $this->record(['status' => 'searching', 'status_changed_at' => '2026-01-01 10:00:00']);
        $this->record(['status' => 'employed', 'status_changed_at' => '2026-06-01 10:00:00']);

        $props = $this->get(route('learners.insertion.index', $this->learner))->assertOk()->viewData('page')['props'];

        $this->assertSame(['employed', 'searching'], array_column($props['records'], 'status'));
        $this->assertSame(['searching', 'internship', 'employed', 'unemployed'], array_column($props['statuses'], 'value'));
        $this->assertTrue(collect($props['statuses'])->firstWhere('value', 'internship')['is_stage']);
        $this->assertTrue(collect($props['statuses'])->firstWhere('value', 'employed')['is_employment']);
    }

    public function test_recording_needs_the_permission_to_update_learners(): void
    {
        $viewer = $this->createAdmin();
        $viewer->permissions()->attach(Permission::firstOrCreate(['slug' => 'learners.view'], ['name' => 'view', 'group' => 'T'])->id);
        $this->actingAs($viewer);

        $this->get(route('learners.insertion.index', $this->learner))->assertOk();
        $this->store(['status' => 'searching'])->assertForbidden();
        $this->assertSame(0, InsertionRecord::count());
    }
}
