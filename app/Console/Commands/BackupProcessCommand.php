<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupProcessCommand extends Command
{
    protected $signature = 'backup:process {backup : Backup UUID}';

    protected $description = 'Execute a pending backup by id (used for background runs)';

    public function handle(BackupService $service): int
    {
        $backup = Backup::query()->find($this->argument('backup'));

        if (! $backup) {
            $this->error('Backup introuvable.');

            return self::FAILURE;
        }

        $service->execute($backup);
        $backup->refresh();

        if ($backup->status === BackupStatus::Success) {
            $this->info('OK '.$backup->disk_path);

            return self::SUCCESS;
        }

        $this->error($backup->error_message ?: 'Échec');

        return self::FAILURE;
    }
}
