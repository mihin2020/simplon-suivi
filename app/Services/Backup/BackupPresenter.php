<?php

namespace App\Services\Backup;

use App\Enums\BackupType;
use App\Models\Backup;

/** Formats a backup for the pages and the progress polling. */
class BackupPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function row(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'type' => $backup->type->value,
            'type_label' => $backup->type->label(),
            'status' => $backup->status->value,
            'status_label' => $backup->status->label(),
            'progress' => (int) ($backup->progress ?? 0),
            'progress_label' => $backup->progress_label,
            'disk_path' => $backup->disk_path,
            'size_bytes' => $backup->size_bytes,
            'size_label' => $this->sizeLabel($backup->size_bytes),
            'remote_synced' => $backup->remote_synced_at !== null,
            'remote_error' => $backup->remote_error,
            'error_message' => $backup->error_message,
            'triggered_by' => $this->author($backup),
            'started_at' => $backup->started_at?->toIso8601String(),
            'finished_at' => $backup->finished_at?->toIso8601String(),
            'created_at' => $backup->created_at?->toIso8601String(),
            'is_downloadable' => $backup->isDownloadable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'status' => $backup->status->value,
            'status_label' => $backup->status->label(),
            'progress' => (int) ($backup->progress ?? 0),
            'progress_label' => $backup->progress_label,
            'error_message' => $backup->error_message,
            'is_downloadable' => $backup->isDownloadable(),
            'size_label' => $this->sizeLabel($backup->size_bytes),
        ];
    }

    private function sizeLabel(?int $bytes): ?string
    {
        return $bytes === null || $bytes <= 0 ? null : round($bytes / 1024 / 1024, 2).' Mo';
    }

    private function author(Backup $backup): ?string
    {
        if ($backup->triggeredBy) {
            return trim($backup->triggeredBy->first_name.' '.$backup->triggeredBy->last_name) ?: $backup->triggeredBy->email;
        }

        return $backup->type === BackupType::Auto ? 'Scheduler' : null;
    }
}
