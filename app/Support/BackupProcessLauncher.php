<?php

namespace App\Support;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class BackupProcessLauncher
{
    /**
     * Start backup processing in a detached OS process so HTTP can poll progress.
     */
    public static function start(string $backupId): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = sprintf(
                    'start /B "" %s %s backup:process %s',
                    escapeshellarg($php),
                    escapeshellarg($artisan),
                    escapeshellarg($backupId)
                );
                pclose(popen($command, 'r'));

                return;
            }

            $command = sprintf(
                'nohup %s %s backup:process %s > /dev/null 2>&1 &',
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($backupId)
            );
            exec($command);
        } catch (\Throwable $e) {
            Log::error('Failed to launch backup process', [
                'backup_id' => $backupId,
                'message' => $e->getMessage(),
            ]);

            // Last resort: same-request execution (progress polling may stall).
            dispatch(function () use ($backupId) {
                $model = Backup::query()->find($backupId);
                if ($model) {
                    app(BackupService::class)->execute($model);
                }
            })->afterResponse();
        }
    }
}
