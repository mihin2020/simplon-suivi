<?php

namespace App\Http\Controllers;

use App\Actions\Backup\StartManualBackup;
use App\Exceptions\ManualBackupRefused;
use App\Http\Requests\Backup\BackupFiltersRequest;
use App\Http\Requests\Backup\UpdateBackupSettingsRequest;
use App\Models\Backup;
use App\Services\Backup\BackupListing;
use App\Services\Backup\BackupPresenter;
use App\Services\BackupService;
use App\Support\BackupSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(BackupFiltersRequest $request, BackupListing $listing): Response
    {
        $filters = $request->filters();

        return Inertia::render('Configuration/Backups', [
            'backups' => $listing->paginate($filters),
            'filters' => $filters,
            'settings' => BackupSettings::all(),
            'remoteConfigured' => BackupSettings::isRemoteConfigured(),
        ]);
    }

    public function store(Request $request, StartManualBackup $action): RedirectResponse
    {
        try {
            $backup = $action->execute($request->user());
        } catch (ManualBackupRefused $refusal) {
            return redirect()->route('configuration.backups')->with('error', $refusal->getMessage());
        }

        return redirect()
            ->route('configuration.backups')
            ->with('success', 'Backup lancé. Suivez la progression à l\'écran.')
            ->with('backup_running_id', $backup->id);
    }

    public function progress(Backup $backup, BackupPresenter $presenter): JsonResponse
    {
        return response()->json($presenter->progress($backup));
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

        return $disk->download($backup->disk_path, $backup->disk_path, ['Content-Type' => 'application/zip']);
    }

    public function destroy(Backup $backup, BackupService $service): RedirectResponse
    {
        $service->deleteBackup($backup);

        return redirect()
            ->route('configuration.backups')
            ->with('success', 'Sauvegarde supprimée.');
    }
}
