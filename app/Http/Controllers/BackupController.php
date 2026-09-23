<?php

namespace App\Http\Controllers;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Http\Requests\Backup\UpdateBackupSettingsRequest;
use App\Models\Backup;
use App\Services\BackupService;
use App\Support\BackupProcessLauncher;
use App\Support\BackupSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        $settings = BackupSettings::all();

        $filters = [
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $backups = Backup::query()
            ->with(['triggeredBy:id,first_name,last_name,email'])
            ->when(
                $filters['type'] !== '' && in_array($filters['type'], ['manual', 'auto'], true),
                fn ($q) => $q->where('type', $filters['type'])
            )
            ->when(
                $filters['status'] !== '' && in_array($filters['status'], ['pending', 'running', 'success', 'failed'], true),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when($filters['date_from'] !== '', function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when($filters['date_to'] !== '', function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('disk_path', 'like', $term)
                        ->orWhereHas('triggeredBy', function ($user) use ($term) {
                            $user->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Backup $backup) => [
                'id' => $backup->id,
                'type' => $backup->type->value,
                'type_label' => $backup->type->label(),
                'status' => $backup->status->value,
                'status_label' => $backup->status->label(),
                'progress' => (int) ($backup->progress ?? 0),
                'progress_label' => $backup->progress_label,
                'disk_path' => $backup->disk_path,
                'size_bytes' => $backup->size_bytes,
                'size_label' => $this->formatSizeLabel($backup->size_bytes),
                'remote_synced' => $backup->remote_synced_at !== null,
                'remote_error' => $backup->remote_error,
                'error_message' => $backup->error_message,
                'triggered_by' => $backup->triggeredBy
                    ? trim($backup->triggeredBy->first_name.' '.$backup->triggeredBy->last_name)
                        ?: $backup->triggeredBy->email
                    : ($backup->type === BackupType::Auto ? 'Scheduler' : null),
                'started_at' => $backup->started_at?->toIso8601String(),
                'finished_at' => $backup->finished_at?->toIso8601String(),
                'created_at' => $backup->created_at?->toIso8601String(),
                'is_downloadable' => $backup->isDownloadable(),
            ]);

        return Inertia::render('Configuration/Backups', [
            'backups' => $backups,
            'filters' => $filters,
            'settings' => $settings,
            'remoteConfigured' => BackupSettings::isRemoteConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $key = 'backup-manual:'.$request->user()->id;
        $minutes = (int) config('backup.manual_rate_limit_minutes', 5);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return redirect()
                ->route('configuration.backups')
                ->with('error', "Veuillez patienter {$seconds} s avant de relancer un backup.");
        }

        $running = Backup::query()
            ->whereIn('status', [BackupStatus::Pending->value, BackupStatus::Running->value])
            ->exists();

        if ($running) {
            return redirect()
                ->route('configuration.backups')
                ->with('error', 'Un backup est déjà en cours.');
        }

        RateLimiter::hit($key, $minutes * 60);

        $backup = Backup::create([
            'type' => BackupType::Manual,
            'status' => BackupStatus::Pending,
            'progress' => 0,
            'progress_label' => 'En file d\'attente…',
            'triggered_by' => $request->user()->id,
        ]);

        $backupId = $backup->id;

        BackupProcessLauncher::start($backupId);

        return redirect()
            ->route('configuration.backups')
            ->with('success', 'Backup lancé. Suivez la progression à l\'écran.')
            ->with('backup_running_id', $backupId);
    }

    public function progress(Backup $backup): JsonResponse
    {
        return response()->json([
            'id' => $backup->id,
            'status' => $backup->status->value,
            'status_label' => $backup->status->label(),
            'progress' => (int) ($backup->progress ?? 0),
            'progress_label' => $backup->progress_label,
            'error_message' => $backup->error_message,
            'is_downloadable' => $backup->isDownloadable(),
            'size_label' => $this->formatSizeLabel($backup->size_bytes),
        ]);
    }

    public function updateSettings(UpdateBackupSettingsRequest $request): RedirectResponse
    {
        BackupSettings::update($request->validated());

        return redirect()
            ->route('configuration.backups')
            ->with('success', 'Paramètres de sauvegarde enregistrés.');
    }

    public function download(Backup $backup): StreamedResponse
    {
        abort_unless($backup->isDownloadable(), 404);

        $disk = Storage::disk(config('backup.local_disk'));

        abort_unless($disk->exists($backup->disk_path), 404);

        return $disk->download(
            $backup->disk_path,
            $backup->disk_path,
            ['Content-Type' => 'application/zip']
        );
    }

    public function destroy(Backup $backup, BackupService $service): RedirectResponse
    {
        $service->deleteBackup($backup);

        return redirect()
            ->route('configuration.backups')
            ->with('success', 'Sauvegarde supprimée.');
    }

    private function formatSizeLabel(?int $bytes): ?string
    {
        if ($bytes === null || $bytes <= 0) {
            return null;
        }

        return round($bytes / 1024 / 1024, 2).' Mo';
    }
}
