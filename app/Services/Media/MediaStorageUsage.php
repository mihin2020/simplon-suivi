<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Support\StorageSize;

/** How much of the Cloudinary storage the registered media use (read from our own table: more reliable than the API). */
class MediaStorageUsage
{
    /** The free plan: 25 GB. */
    private const LIMIT_BYTES = 25 * 1024 * 1024 * 1024;

    /** @return array<string, mixed> */
    public function fromDatabase(): array
    {
        $used = (int) Media::sum('file_size') * 1024; // file_size is stored in KB
        $percent = min(100, round($used / self::LIMIT_BYTES * 100, 2));

        // A tiny but real usage still shows on the gauge.
        if ($percent == 0 && $used > 0) {
            $percent = 0.01;
        }

        return [
            'storage_used' => $used,
            'storage_limit' => self::LIMIT_BYTES,
            'storage_used_formatted' => StorageSize::format($used),
            'storage_limit_formatted' => '25 GB',
            'storage_remaining' => max(0, self::LIMIT_BYTES - $used),
            'storage_remaining_formatted' => StorageSize::format(max(0, self::LIMIT_BYTES - $used)),
            'percent_used' => $percent,
            'bandwidth_used' => 0,
            'bandwidth_formatted' => '0 B',
            'requests' => Media::count(),
            'alert_level' => StorageSize::alertLevel($percent),
        ];
    }
}
