<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'status',
        'progress',
        'progress_label',
        'disk_path',
        'size_bytes',
        'remote_path',
        'remote_synced_at',
        'remote_error',
        'error_message',
        'triggered_by',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'type' => BackupType::class,
        'status' => BackupStatus::class,
        'progress' => 'integer',
        'size_bytes' => 'integer',
        'remote_synced_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === BackupStatus::Success
            && filled($this->disk_path);
    }
}
