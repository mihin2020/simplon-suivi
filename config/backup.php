<?php

return [

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),

    /*
     * Optional override. When empty, MysqldumpResolver auto-detects:
     * - Windows: Laragon / XAMPP / WAMP / PATH
     * - Linux: mariadb-dump (preferred) then mysqldump
     */

    'local_disk' => env('BACKUP_DISK', 'backups'),

    'remote_enabled' => (bool) env('BACKUP_REMOTE_ENABLED', false),

    'remote_disk' => env('BACKUP_REMOTE_DISK', 'backup_remote'),

    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 14),

    'min_free_bytes' => (int) env('BACKUP_MIN_FREE_BYTES', 50 * 1024 * 1024),

    /*
     * Root directory of application files included in the archive.
     * Defaults to storage/app (excludes backups/ and backup-tmp/ at copy time).
     */
    'files_root' => env('BACKUP_FILES_ROOT'),

    'manual_rate_limit_minutes' => 5,

];
