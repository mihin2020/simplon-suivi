<?php

namespace App\Support;

/** Human-readable storage sizes and the alert level of a storage gauge. */
final class StorageSize
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    public static function format(int $bytes, int $precision = 2): string
    {
        $bytes = max($bytes, 0);
        $power = min((int) floor(($bytes ? log($bytes) : 0) / log(1024)), count(self::UNITS) - 1);

        return round($bytes / 1024 ** $power, $precision).' '.self::UNITS[$power];
    }

    /** "critical" from 95 %, "warning" from 80 %, otherwise "normal". */
    public static function alertLevel(float $percentUsed): string
    {
        return match (true) {
            $percentUsed >= 95 => 'critical',
            $percentUsed >= 80 => 'warning',
            default => 'normal',
        };
    }
}
