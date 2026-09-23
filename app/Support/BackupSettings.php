<?php

namespace App\Support;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\AppSetting;
use App\Models\Backup;
use Carbon\Carbon;

class BackupSettings
{
    public const AUTO_ENABLED = 'backup.auto_enabled';

    public const INTERVAL_DAYS = 'backup.interval_days';

    public const FREQUENCY = 'backup.frequency'; // legacy

    public const RUN_AT = 'backup.run_at';

    public const RETENTION_COUNT = 'backup.retention_count';

    public const NOTIFY_EMAIL = 'backup.notify_email';

    public const REMOTE_ENABLED = 'backup.remote_enabled';

    /**
     * @return array{
     *     auto_enabled: bool,
     *     interval_days: int,
     *     run_at: string,
     *     retention_count: int,
     *     notify_email: string|null,
     *     remote_enabled: bool
     * }
     */
    public static function all(): array
    {
        return [
            'auto_enabled' => filter_var(
                AppSetting::get(self::AUTO_ENABLED, '0'),
                FILTER_VALIDATE_BOOLEAN
            ),
            'interval_days' => self::intervalDays(),
            'run_at' => (string) AppSetting::get(self::RUN_AT, '02:00'),
            'retention_count' => (int) AppSetting::get(
                self::RETENTION_COUNT,
                (string) config('backup.retention_count', 14)
            ),
            'notify_email' => self::notifyEmail(),
            'remote_enabled' => self::remoteEnabled(),
        ];
    }

    public static function intervalDays(): int
    {
        $raw = AppSetting::get(self::INTERVAL_DAYS);

        if ($raw !== null && $raw !== '') {
            return max(1, min(365, (int) $raw));
        }

        // Legacy frequency → days
        return match ((string) AppSetting::get(self::FREQUENCY, 'daily')) {
            'weekly' => 7,
            'monthly' => 30,
            default => 1,
        };
    }

    public static function notifyEmail(): ?string
    {
        $email = trim((string) AppSetting::get(self::NOTIFY_EMAIL, ''));

        return $email !== '' ? $email : null;
    }

    public static function remoteEnabled(): bool
    {
        $fromSettings = AppSetting::get(self::REMOTE_ENABLED);

        if ($fromSettings !== null) {
            return filter_var($fromSettings, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('backup.remote_enabled', false);
    }

    public static function isRemoteConfigured(): bool
    {
        return filled(config('filesystems.disks.backup_remote.key'))
            && filled(config('filesystems.disks.backup_remote.secret'))
            && filled(config('filesystems.disks.backup_remote.bucket'))
            && filled(config('filesystems.disks.backup_remote.endpoint'));
    }

    /**
     * Whether an automatic backup should run at the current minute.
     */
    public static function shouldRunAutoNow(?Carbon $now = null): bool
    {
        $settings = self::all();

        if (! $settings['auto_enabled']) {
            return false;
        }

        $now ??= now();
        [$hour, $minute] = array_pad(explode(':', $settings['run_at']), 2, '0');

        if ((int) $now->format('H') !== (int) $hour || (int) $now->format('i') !== (int) $minute) {
            return false;
        }

        $interval = max(1, $settings['interval_days']);

        $lastFinished = Backup::query()
            ->where('type', BackupType::Auto)
            ->where('status', BackupStatus::Success)
            ->orderByDesc('finished_at')
            ->value('finished_at');

        if ($lastFinished === null) {
            return true;
        }

        $daysSince = Carbon::parse($lastFinished)->startOfDay()->diffInDays($now->copy()->startOfDay());

        return $daysSince >= $interval;
    }

    /**
     * @param  array{
     *     auto_enabled?: bool,
     *     interval_days?: int,
     *     run_at?: string,
     *     retention_count?: int,
     *     notify_email?: string|null,
     *     remote_enabled?: bool
     *  }  $data
     */
    public static function update(array $data): void
    {
        if (array_key_exists('auto_enabled', $data)) {
            AppSetting::set(self::AUTO_ENABLED, $data['auto_enabled'] ? '1' : '0');
        }

        if (array_key_exists('interval_days', $data)) {
            AppSetting::set(self::INTERVAL_DAYS, (string) max(1, min(365, (int) $data['interval_days'])));
        }

        if (array_key_exists('run_at', $data)) {
            AppSetting::set(self::RUN_AT, (string) $data['run_at']);
        }

        if (array_key_exists('retention_count', $data)) {
            AppSetting::set(self::RETENTION_COUNT, (string) (int) $data['retention_count']);
        }

        if (array_key_exists('notify_email', $data)) {
            AppSetting::set(self::NOTIFY_EMAIL, (string) ($data['notify_email'] ?? ''));
        }

        if (array_key_exists('remote_enabled', $data)) {
            AppSetting::set(self::REMOTE_ENABLED, $data['remote_enabled'] ? '1' : '0');
        }
    }
}
