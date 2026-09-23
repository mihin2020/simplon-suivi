<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class MysqldumpResolver
{
    /**
     * Resolve dump binary for local Windows (Laragon/XAMPP) and Linux production (MariaDB/MySQL).
     */
    public static function path(): string
    {
        $configured = trim((string) config('backup.mysqldump_path', ''));

        if ($configured !== '' && $configured !== 'mysqldump' && File::exists($configured)) {
            return $configured;
        }

        if ($configured === 'mysqldump' || $configured === '') {
            return self::detect() ?? 'mysqldump';
        }

        // Configured but missing on disk — try auto-detect before failing later
        return self::detect() ?? $configured;
    }

    public static function detect(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (self::windowsCandidates() as $candidate) {
                if (File::exists($candidate)) {
                    return $candidate;
                }
            }

            return self::which('mysqldump.exe')
                ?? self::which('mysqldump')
                ?? self::which('mariadb-dump.exe')
                ?? self::which('mariadb-dump');
        }

        // Prefer mariadb-dump on modern MariaDB servers (mysqldump is deprecated there).
        foreach ([
            '/usr/bin/mariadb-dump',
            '/usr/local/bin/mariadb-dump',
            '/bin/mariadb-dump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/bin/mysqldump',
        ] as $candidate) {
            if (File::exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return self::which('mariadb-dump') ?? self::which('mysqldump');
    }

    /**
     * @return list<string>
     */
    private static function windowsCandidates(): array
    {
        $candidates = [];

        $laragonRoot = 'C:\\laragon\\bin\\mysql';
        if (is_dir($laragonRoot)) {
            $dirs = glob($laragonRoot.'\\mysql-*', GLOB_ONLYDIR) ?: [];
            rsort($dirs);
            foreach ($dirs as $dir) {
                $candidates[] = $dir.'\\bin\\mysqldump.exe';
            }
        }

        $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

        $wampRoots = glob('C:\\wamp64\\bin\\mysql\\mysql*', GLOB_ONLYDIR) ?: [];
        rsort($wampRoots);
        foreach ($wampRoots as $dir) {
            $candidates[] = $dir.'\\bin\\mysqldump.exe';
        }

        return $candidates;
    }

    private static function which(string $binary): ?string
    {
        try {
            $result = PHP_OS_FAMILY === 'Windows'
                ? Process::run(['where', $binary])
                : Process::run(['which', $binary]);

            if (! $result->successful()) {
                return null;
            }

            $line = trim(explode("\n", str_replace("\r", '', $result->output()))[0] ?? '');

            return ($line !== '' && File::exists($line)) ? $line : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
