<?php

namespace App\Actions\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\ManualBackupRefused;
use App\Models\Backup;
use App\Models\User;
use App\Support\BackupProcessLauncher;
use Illuminate\Support\Facades\RateLimiter;

class StartManualBackup
{
    public function __construct(private readonly BackupProcessLauncher $launcher) {}

    /**
     * Queues a backup and starts its processing in a detached process.
     *
     * @throws ManualBackupRefused when the user launched one too recently or another backup is still in progress.
     */
    public function execute(User $user): Backup
    {
        $key = 'backup-manual:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ManualBackupRefused::tooSoon(RateLimiter::availableIn($key));
        }

        $isRunning = Backup::query()
            ->whereIn('status', [BackupStatus::Pending, BackupStatus::Running])
            ->exists();

        if ($isRunning) {
            throw ManualBackupRefused::alreadyRunning();
        }

        RateLimiter::hit($key, (int) config('backup.manual_rate_limit_minutes', 5) * 60);

        $backup = Backup::create([
            'type' => BackupType::Manual,
            'status' => BackupStatus::Pending,
            'progress' => 0,
            'progress_label' => 'En file d\'attente…',
            'triggered_by' => $user->id,
        ]);

        $this->launcher->start($backup->id);

        return $backup;
    }
}
