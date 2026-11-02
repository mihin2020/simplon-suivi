<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\Permission;
use App\Models\User;
use App\Support\BackupProcessLauncher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pins the page, the launch, the follow-up, the download and the deletion of database backups. */
class BackupManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('backup.local_disk'));
        $this->actingAsSuperAdmin();
    }

    private function backup(array $attributes = []): Backup
    {
        return Backup::create([
            'type' => BackupType::Manual, 'status' => BackupStatus::Success, 'progress' => 100,
            'disk_path' => 'backup.zip', 'size_bytes' => 5 * 1024 * 1024, ...$attributes,
        ]);
    }

    private function rows(string $query = ''): array
    {
        return $this->get(route('configuration.backups').$query)->assertOk()->viewData('page')['props']['backups']['data'];
    }

    // ── Page ───────────────────────────────────────────────────────────────

    public function test_the_page_describes_each_backup(): void
    {
        $author = User::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traoré']);
        $this->backup(['triggered_by' => $author->id, 'started_at' => '2026-11-01 08:00:00']);
        $this->backup(['type' => BackupType::Auto, 'status' => BackupStatus::Failed, 'disk_path' => null, 'size_bytes' => null, 'progress' => 0, 'error_message' => 'Disque plein']);

        $rows = collect($this->rows())->keyBy('status');

        $this->assertSame('Awa Traoré', $rows['success']['triggered_by']);
        $this->assertSame('5 Mo', $rows['success']['size_label']);
        $this->assertTrue($rows['success']['is_downloadable']);
        $this->assertFalse($rows['success']['remote_synced']);
        $this->assertSame('2026-11-01', substr($rows['success']['started_at'], 0, 10));
        $this->assertSame('Scheduler', $rows['failed']['triggered_by'], 'an automatic backup without author is the scheduler');
        $this->assertNull($rows['failed']['size_label']);
        $this->assertSame(0, $rows['failed']['progress']);
        $this->assertFalse($rows['failed']['is_downloadable']);
        $this->assertSame('Disque plein', $rows['failed']['error_message']);
    }

    public function test_the_page_filters_and_echoes_the_filters(): void
    {
        $author = User::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traoré', 'email' => 'awa@example.org']);
        $manual = $this->backup(['disk_path' => 'manual.zip', 'triggered_by' => $author->id]);
        $auto = $this->backup(['type' => BackupType::Auto, 'status' => BackupStatus::Failed, 'disk_path' => 'auto.zip']);
        Backup::query()->whereKey($manual->id)->update(['created_at' => '2026-10-01 10:00:00']);
        Backup::query()->whereKey($auto->id)->update(['created_at' => '2026-11-01 10:00:00']);

        $ids = fn (string $query) => array_column($this->rows($query), 'id');

        $this->assertEqualsCanonicalizing([$manual->id, $auto->id], $ids(''));
        $this->assertSame([$auto->id], $ids('?type=auto'));
        $this->assertSame([$manual->id], $ids('?status=success'));
        $this->assertSame([$auto->id], $ids('?date_from=2026-10-15'));
        $this->assertSame([$manual->id], $ids('?date_to=2026-10-15'));
        $this->assertSame([$auto->id], $ids('?search=auto.zip'));
        $this->assertSame([$manual->id], $ids('?search=awa@example'), 'searches the author as well');
        $this->assertEqualsCanonicalizing([$manual->id, $auto->id], $ids('?type=weird&status=weird'), 'unknown values are ignored');

        $props = $this->get(route('configuration.backups', ['type' => 'auto', 'search' => 'x']))->viewData('page')['props'];
        $this->assertSame(['type' => 'auto', 'status' => '', 'date_from' => '', 'date_to' => '', 'search' => 'x'], $props['filters']);
    }

    public function test_the_page_is_paginated_newest_first(): void
    {
        foreach (range(1, 12) as $day) {
            $this->backup(['disk_path' => "b{$day}.zip"])->forceFill(['created_at' => Carbon::parse('2026-10-01')->addDays($day)])->save();
        }

        $page = $this->get(route('configuration.backups'))->viewData('page')['props']['backups'];

        $this->assertCount(10, $page['data']);
        $this->assertSame(12, $page['total']);
        $this->assertSame('b12.zip', $page['data'][0]['disk_path']);
    }

    // ── Launch ─────────────────────────────────────────────────────────────

    public function test_a_manual_backup_is_queued_and_launched(): void
    {
        RateLimiter::clear('backup-manual:'.auth()->id());
        $launcher = $this->mock(BackupProcessLauncher::class);
        $launcher->shouldReceive('start')->once();

        $response = $this->post(route('configuration.backups.store'));

        $backup = Backup::firstOrFail();
        $response->assertRedirect(route('configuration.backups'))
            ->assertSessionHas('success', 'Backup lancé. Suivez la progression à l\'écran.')
            ->assertSessionHas('backup_running_id', $backup->id);
        $this->assertSame([BackupType::Manual, BackupStatus::Pending, 0, auth()->id()], [$backup->type, $backup->status, $backup->progress, $backup->triggered_by]);
        $this->assertSame('En file d\'attente…', $backup->progress_label);
    }

    public function test_a_second_launch_is_refused_while_one_is_running(): void
    {
        RateLimiter::clear('backup-manual:'.auth()->id());
        $this->backup(['status' => BackupStatus::Running, 'progress' => 40]);
        $this->mock(BackupProcessLauncher::class)->shouldNotReceive('start');

        $this->post(route('configuration.backups.store'))
            ->assertRedirect(route('configuration.backups'))->assertSessionHas('error', 'Un backup est déjà en cours.');

        $this->assertSame(1, Backup::count());
    }

    public function test_launches_are_rate_limited_per_user(): void
    {
        RateLimiter::clear('backup-manual:'.auth()->id());
        $this->mock(BackupProcessLauncher::class)->shouldReceive('start')->once();

        $this->post(route('configuration.backups.store'))->assertSessionHas('success');
        Backup::query()->update(['status' => BackupStatus::Success]);

        $this->post(route('configuration.backups.store'))
            ->assertRedirect(route('configuration.backups'))
            ->assertSessionHas('error', fn (string $message) => str_starts_with($message, 'Veuillez patienter ') && str_ends_with($message, ' s avant de relancer un backup.'));

        $this->assertSame(1, Backup::count());
    }

    // ── Follow-up, download, deletion, settings ────────────────────────────

    public function test_the_progress_is_exposed_as_json(): void
    {
        $backup = $this->backup(['status' => BackupStatus::Running, 'progress' => 40, 'progress_label' => 'Compression', 'disk_path' => null, 'size_bytes' => null]);

        $this->getJson(route('configuration.backups.progress', $backup))->assertOk()->assertExactJson([
            'id' => $backup->id, 'status' => 'running', 'status_label' => BackupStatus::Running->label(), 'progress' => 40,
            'progress_label' => 'Compression', 'error_message' => null, 'is_downloadable' => false, 'size_label' => null,
        ]);
    }

    public function test_a_finished_backup_is_downloaded_as_a_zip(): void
    {
        Storage::disk(config('backup.local_disk'))->put('backup.zip', 'zip-content');
        $backup = $this->backup();

        $response = $this->get(route('configuration.backups.download', $backup))->assertOk();

        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('backup.zip', $response->headers->get('Content-Disposition'));
    }

    public function test_a_backup_cannot_be_downloaded_when_unfinished_or_missing(): void
    {
        $this->get(route('configuration.backups.download', $this->backup(['status' => BackupStatus::Running])))->assertNotFound();
        $this->get(route('configuration.backups.download', $this->backup(['disk_path' => 'gone.zip'])))->assertNotFound();
    }

    public function test_deleting_a_backup_removes_its_archive(): void
    {
        Storage::disk(config('backup.local_disk'))->put('backup.zip', 'zip-content');
        $backup = $this->backup();

        $this->delete(route('configuration.backups.destroy', $backup))
            ->assertRedirect(route('configuration.backups'))->assertSessionHas('success', 'Sauvegarde supprimée.');

        $this->assertNull(Backup::find($backup->id));
        Storage::disk(config('backup.local_disk'))->assertMissing('backup.zip');
    }

    public function test_each_action_needs_its_permission(): void
    {
        $backup = $this->backup();
        $admin = $this->createAdmin();
        $admin->permissions()->attach(Permission::firstOrCreate(['slug' => 'backup.view'], ['name' => 'view', 'group' => 'T'])->id);
        $this->actingAs($admin);
        $this->mock(BackupProcessLauncher::class)->shouldNotReceive('start');

        $this->get(route('configuration.backups'))->assertOk();
        $this->get(route('configuration.backups.progress', $backup))->assertOk();
        $this->post(route('configuration.backups.store'))->assertForbidden();
        $this->delete(route('configuration.backups.destroy', $backup))->assertForbidden();
        $this->assertNotNull(Backup::find($backup->id));
    }
}
