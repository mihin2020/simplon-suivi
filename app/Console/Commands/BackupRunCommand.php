<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Services\BackupService;
use App\Support\BackupSettings;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--type=auto : manual|auto}';

    protected $description = 'Create an application backup (database + storage/app files)';

    public function handle(BackupService $service): int
    {
        $typeValue = $this->option('type') === 'manual' ? BackupType::Manual : BackupType::Auto;

        if ($typeValue === BackupType::Auto && ! BackupSettings::all()['auto_enabled']) {
            $this->info('Backups automatiques désactivés — rien à faire.');

            return self::SUCCESS;
        }

        $running = Backup::query()
            ->whereIn('status', [BackupStatus::Pending->value, BackupStatus::Running->value])
            ->exists();

        if ($running) {
            $this->warn('Un backup est déjà en cours.');

            return self::FAILURE;
        }

        $backup = Backup::create([
            'type' => $typeValue,
            'status' => BackupStatus::Pending,
            'progress' => 0,
            'progress_label' => 'En attente…',
            'triggered_by' => null,
        ]);

        $service->execute($backup);
        $backup->refresh();

        if ($backup->status === BackupStatus::Success) {
            $this->info('Backup terminé : '.$backup->disk_path);

            return self::SUCCESS;
        }

        $this->error('Backup échoué : '.$backup->error_message);

        return self::FAILURE;
    }
}
