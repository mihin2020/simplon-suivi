<?php

namespace App\Services;

use App\Enums\BackupStatus;
use App\Mail\BackupCompletedMail;
use App\Mail\BackupFailedMail;
use App\Models\Backup;
use App\Support\BackupSettings;
use App\Support\MysqldumpRunner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupService
{
    public function execute(Backup $backup): void
    {
        $this->markProgress($backup, 2, 'Préparation…', BackupStatus::Running);

        $workDir = storage_path('app/backup-tmp/'.Str::uuid()->toString());
        $sqlPath = null;
        $absoluteZip = null;

        try {
            $this->assertEnoughDiskSpace();
            $this->markProgress($backup, 5, 'Préparation du dossier temporaire…');
            File::ensureDirectoryExists($workDir);
            File::ensureDirectoryExists(storage_path('app/backups'));

            $this->markProgress($backup, 10, 'Export de la base de données…');
            $sqlPath = $this->dumpDatabase($workDir);
            $this->markProgress($backup, 40, 'Base exportée — création de l\'archive…');

            $filename = 'backup-'.now()->format('Y-m-d-His').'.zip';
            $absoluteZip = storage_path('app/backups/'.$filename);

            $this->buildArchive($backup, $workDir, $sqlPath, $absoluteZip);

            $this->markProgress($backup, 88, 'Finalisation de l\'archive…');
            $size = File::size($absoluteZip);
            $backup->update([
                'disk_path' => $filename,
                'size_bytes' => $size,
            ]);

            $this->markProgress($backup, 92, 'Envoi vers le cloud…');
            $this->syncRemote($backup, $filename, $absoluteZip);

            $this->markProgress($backup, 97, 'Application de la rétention…');
            $this->applyRetention();

            $backup->update([
                'status' => BackupStatus::Success,
                'progress' => 100,
                'progress_label' => 'Terminé',
                'finished_at' => now(),
            ]);

            $this->notifySuccess($backup->fresh());
        } catch (Throwable $e) {
            Log::error('Backup failed', [
                'backup_id' => $backup->id,
                'message' => $e->getMessage(),
            ]);

            $backup->update([
                'status' => BackupStatus::Failed,
                'progress' => 0,
                'progress_label' => 'Échec',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'finished_at' => now(),
            ]);

            if ($absoluteZip && File::exists($absoluteZip) && ! $backup->disk_path) {
                @File::delete($absoluteZip);
            }

            $this->notifyFailure($backup->fresh());
        } finally {
            if (File::isDirectory($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    public function deleteBackup(Backup $backup): void
    {
        if ($backup->disk_path) {
            Storage::disk(config('backup.local_disk'))->delete($backup->disk_path);
        }

        if ($backup->remote_path && BackupSettings::isRemoteConfigured()) {
            try {
                Storage::disk(config('backup.remote_disk'))->delete($backup->remote_path);
            } catch (Throwable $e) {
                Log::warning('Failed to delete remote backup object', [
                    'backup_id' => $backup->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $backup->delete();
    }

    public function applyRetention(): void
    {
        $keep = max(1, (int) BackupSettings::all()['retention_count']);

        $obsolete = Backup::query()
            ->where('status', BackupStatus::Success)
            ->orderByDesc('created_at')
            ->skip($keep)
            ->take(100)
            ->get();

        foreach ($obsolete as $backup) {
            $this->deleteBackup($backup);
        }
    }

    private function markProgress(Backup $backup, int $progress, string $label, ?BackupStatus $status = null): void
    {
        $payload = [
            'progress' => max(0, min(100, $progress)),
            'progress_label' => $label,
            'updated_at' => now(),
        ];

        if ($status) {
            $payload['status'] = $status->value;
            if ($status === BackupStatus::Running && ! $backup->started_at) {
                $payload['started_at'] = now();
            }
        }

        // Force immediate DB write so concurrent HTTP polls see progress.
        Backup::query()->whereKey($backup->id)->update($payload);
        $backup->fill($payload);
        if ($status) {
            $backup->status = $status;
        }
    }

    private function assertEnoughDiskSpace(): void
    {
        $path = storage_path('app');
        $free = @disk_free_space($path);
        $min = (int) config('backup.min_free_bytes', 50 * 1024 * 1024);

        if ($free !== false && $free < $min) {
            throw new RuntimeException(
                'Espace disque insuffisant pour créer une sauvegarde (minimum '.round($min / 1024 / 1024).' Mo libres, disponible : '.round($free / 1024 / 1024).' Mo).'
            );
        }
    }

    private function dumpDatabase(string $workDir): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $database = config("database.connections.{$connection}.database");
            if (! is_string($database) || ! File::exists($database)) {
                throw new RuntimeException('Fichier SQLite introuvable pour le dump.');
            }
            $target = $workDir.'/database.sqlite';
            File::copy($database, $target);

            return $target;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Driver de base de données non supporté pour le backup : {$driver}");
        }

        $sqlPath = $workDir.'/database.sql';

        return MysqldumpRunner::dump($sqlPath);
    }

    /**
     * Build zip in one pass: SQL + files from storage (no intermediate full copy).
     */
    private function buildArchive(Backup $backup, string $workDir, string $sqlPath, string $absoluteZip): void
    {
        $manifestPath = $workDir.'/manifest.json';
        File::put($manifestPath, json_encode([
            'app' => config('app.name'),
            'app_url' => config('app.url'),
            'backup_id' => $backup->id,
            'type' => $backup->type->value,
            'created_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ], JSON_UNESCAPED_SLASHES));

        $zip = new ZipArchive;
        if ($zip->open($absoluteZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer l\'archive ZIP.');
        }

        $dbEntry = str_ends_with($sqlPath, '.sqlite') ? 'database.sqlite' : 'database.sql';
        $zip->addFile($sqlPath, $dbEntry);
        $zip->addFile($manifestPath, 'manifest.json');

        $source = config('backup.files_root') ?: storage_path('app');
        if (! File::isDirectory($source)) {
            File::ensureDirectoryExists($source);
        }

        $excludeRoots = array_filter([
            realpath(storage_path('app/backups')) ?: storage_path('app/backups'),
            realpath(storage_path('app/backup-tmp')) ?: storage_path('app/backup-tmp'),
        ]);

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $real = realpath($path) ?: $path;

            foreach ($excludeRoots as $excluded) {
                $excluded = rtrim($excluded, DIRECTORY_SEPARATOR);
                if ($real === $excluded || str_starts_with($real, $excluded.DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $relative = ltrim(substr($path, strlen($source)), DIRECTORY_SEPARATOR.'\\/');
            if ($relative === '') {
                continue;
            }

            // Skip ad-hoc SQL dumps left at storage/app root (recovery artefacts).
            if (! str_contains($relative, DIRECTORY_SEPARATOR) && ! str_contains($relative, '/')
                && str_ends_with(strtolower($relative), '.sql')) {
                continue;
            }

            $files[] = [$path, 'files/'.str_replace('\\', '/', $relative)];
        }

        $total = count($files);
        $done = 0;
        $lastReported = 35;

        foreach ($files as [$path, $entry]) {
            $zip->addFile($path, $entry);
            // Prefer store for already-compressed media (faster).
            $index = $zip->numFiles - 1;
            if ($index >= 0) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'pdf', 'mp4', 'mp3'], true)) {
                    $zip->setCompressionIndex($index, ZipArchive::CM_STORE);
                } else {
                    $zip->setCompressionIndex($index, ZipArchive::CM_DEFLATE, 1);
                }
            }

            $done++;
            if ($total > 0 && ($done % 10 === 0 || $done === $total)) {
                $pct = 40 + (int) floor(($done / $total) * 45);
                if ($pct > $lastReported) {
                    $this->markProgress(
                        $backup,
                        $pct,
                        "Archivage des fichiers… {$done}/{$total}"
                    );
                    $lastReported = $pct;
                }
            }
        }

        $zip->close();

        if (! File::exists($absoluteZip) || File::size($absoluteZip) === 0) {
            throw new RuntimeException('Archive ZIP introuvable ou vide après création.');
        }

        $this->markProgress($backup, 86, 'Archive créée');
    }

    private function syncRemote(Backup $backup, string $filename, string $absoluteZip): void
    {
        $settings = BackupSettings::all();

        if (! $settings['remote_enabled'] || ! BackupSettings::isRemoteConfigured()) {
            return;
        }

        try {
            $remotePath = 'backups/'.$filename;
            $stream = fopen($absoluteZip, 'r');
            if ($stream === false) {
                throw new RuntimeException('Impossible de lire l\'archive pour l\'upload distant.');
            }

            try {
                Storage::disk(config('backup.remote_disk'))->writeStream($remotePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $backup->update([
                'remote_path' => $remotePath,
                'remote_synced_at' => now(),
                'remote_error' => null,
            ]);
        } catch (Throwable $e) {
            $backup->update([
                'remote_error' => Str::limit($e->getMessage(), 2000),
            ]);

            Log::warning('Remote backup sync failed', [
                'backup_id' => $backup->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function notifySuccess(Backup $backup): void
    {
        $email = BackupSettings::notifyEmail();
        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new BackupCompletedMail($backup));
    }

    private function notifyFailure(Backup $backup): void
    {
        $email = BackupSettings::notifyEmail();
        if (! $email) {
            return;
        }

        Mail::to($email)->queue(new BackupFailedMail($backup));
    }
}
