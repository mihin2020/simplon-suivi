<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\UserRole;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use App\Support\BackupSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BackupTest extends TestCase
{
    public function test_super_admin_can_view_backups_page(): void
    {
        $this->actingAsSuperAdmin()
            ->get('/configuration/backups')
            ->assertOk();
    }

    public function test_trainer_cannot_view_backups_page(): void
    {
        $this->actingAsTrainer()
            ->get('/configuration/backups')
            ->assertForbidden();
    }

    public function test_admin_without_permission_cannot_view_backups(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/configuration/backups')
            ->assertForbidden();
    }

    public function test_can_update_backup_settings(): void
    {
        $this->actingAsSuperAdmin()
            ->from('/configuration/backups')
            ->put('/configuration/backups/settings', [
                'auto_enabled' => true,
                'interval_days' => 3,
                'run_at' => '03:30',
                'retention_count' => 7,
                'notify_email' => 'ops@example.com',
                'remote_enabled' => false,
            ])
            ->assertRedirect(route('configuration.backups'));

        $settings = BackupSettings::all();
        $this->assertTrue($settings['auto_enabled']);
        $this->assertSame(3, $settings['interval_days']);
        $this->assertSame('03:30', $settings['run_at']);
        $this->assertSame(7, $settings['retention_count']);
        $this->assertSame('ops@example.com', $settings['notify_email']);
    }

    public function test_manual_backup_creates_archive_on_sqlite(): void
    {
        Mail::fake();
        config(['backup.min_free_bytes' => 1024]);

        $tinyFiles = storage_path('framework/testing/backup-files');
        File::deleteDirectory($tinyFiles);
        File::ensureDirectoryExists($tinyFiles.'/sample');
        File::put($tinyFiles.'/sample/hello.txt', 'ok');
        config(['backup.files_root' => $tinyFiles]);

        BackupSettings::update([
            'notify_email' => null,
            'remote_enabled' => false,
            'retention_count' => 14,
        ]);

        config(['backup.remote_enabled' => false]);

        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)
            ->from('/configuration/backups')
            ->post('/configuration/backups');
        $response->assertRedirect(route('configuration.backups'));

        $backup = Backup::query()->latest()->first();
        $this->assertNotNull($backup);

        if (in_array($backup->status, [BackupStatus::Pending, BackupStatus::Running], true)) {
            app(BackupService::class)->execute($backup);
            $backup->refresh();
        }

        $this->assertSame(BackupStatus::Success, $backup->status);
        $this->assertNotNull($backup->disk_path);
        $this->assertGreaterThan(0, $backup->size_bytes);
        $this->assertFileExists(storage_path('app/backups/'.$backup->disk_path));
    }

    public function test_retention_purges_old_backups(): void
    {
        BackupSettings::update(['retention_count' => 2, 'remote_enabled' => false]);

        @mkdir(storage_path('app/backups'), 0777, true);

        for ($i = 0; $i < 4; $i++) {
            $backup = Backup::create([
                'type' => BackupType::Manual,
                'status' => BackupStatus::Success,
                'disk_path' => "old-{$i}.zip",
                'size_bytes' => 10,
            ]);
            $backup->forceFill([
                'created_at' => now()->subMinutes(40 - $i),
                'updated_at' => now()->subMinutes(40 - $i),
            ])->save();
            file_put_contents(storage_path('app/backups/'.$backup->disk_path), 'x');
        }

        app(BackupService::class)->applyRetention();

        $this->assertSame(2, Backup::query()->where('status', BackupStatus::Success)->count());
    }
}
