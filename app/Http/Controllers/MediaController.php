<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\FormationLink;
use App\Models\Media;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    private CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    // Afficher la médiathèque d'une formation (Inertia)
    public function index(Formation $formation): Response
    {
        $this->authorize('view', $formation);

        $medias = Media::where('formation_id', $formation->id)
            ->with('uploader:id,first_name,last_name')
            ->orderBy('album')
            ->orderByDesc('created_at')
            ->get();

        $albums = Media::where('formation_id', $formation->id)
            ->select('album')
            ->distinct()
            ->whereNotNull('album')
            ->orderBy('album')
            ->pluck('album');

        // Calculer l'utilisation depuis la BDD locale (plus fiable que l'API Cloudinary)
        $usage = $this->getUsageFromDatabase();

        $links = FormationLink::where('formation_id', $formation->id)
            ->with('creator:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Formations/MediaLibrary', [
            'formation' => $formation->load('project:id,name'),
            'medias' => $medias,
            'albums' => $albums,
            'links' => $links,
            'cloudinaryUsage' => $usage,
            'cloudName' => config('cloudinary.cloud_name'),
            'uploadPreset' => config('cloudinary.upload_preset', 'simplon_medias'),
        ]);
    }

    // Calculer l'utilisation du stockage depuis la base de données
    private function getUsageFromDatabase(): array
    {
        $totalKB = Media::sum('file_size') ?? 0;
        $totalBytes = $totalKB * 1024; // file_size est en KB
        $storageLimit = 25 * 1024 * 1024 * 1024; // 25 GB en bytes

        $percentUsed = $storageLimit > 0 ? min(100, round(($totalBytes / $storageLimit) * 100, 2)) : 0;
        
        // Si le pourcentage est très petit mais non nul, afficher au moins 0.01%
        if ($percentUsed == 0 && $totalBytes > 0) {
            $percentUsed = 0.01;
        }

        \Log::info('Stockage calculé', [
            'total_kb' => $totalKB,
            'total_bytes' => $totalBytes,
            'count' => Media::count(),
            'percent' => $percentUsed,
        ]);

        return [
            'storage_used' => $totalBytes,
            'storage_limit' => $storageLimit,
            'storage_used_formatted' => $this->formatBytes($totalBytes),
            'storage_limit_formatted' => '25 GB',
            'storage_remaining' => max(0, $storageLimit - $totalBytes),
            'storage_remaining_formatted' => $this->formatBytes(max(0, $storageLimit - $totalBytes)),
            'percent_used' => $percentUsed,
            'bandwidth_used' => 0,
            'bandwidth_formatted' => '0 B',
            'requests' => Media::count(),
            'alert_level' => $this->getAlertLevel($percentUsed),
        ];
    }

    // Formatter les bytes
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // Déterminer le niveau d'alerte
    private function getAlertLevel(float $percent): string
    {
        if ($percent >= 95) {
            return 'critical';
        } elseif ($percent >= 80) {
            return 'warning';
        }
        return 'normal';
    }

    // Sauvegarder un médias uploadé sur Cloudinary
    public function store(Request $request, Formation $formation): JsonResponse
    {
        $this->authorize('update', $formation);

        $validated = $request->validate([
            'cloudinary_public_id' => 'required|string',
            'url' => 'required|url',
            'thumbnail_url' => 'nullable|url',
            'type' => 'required|in:photo,video',
            'title' => 'nullable|string|max:255',
            'album' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer', // en KB
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'duration' => 'nullable|integer', // en secondes
            'format' => 'nullable|string|max:20',
        ]);

        $media = Media::create([
            'formation_id' => $formation->id,
            'uploaded_by' => Auth::id(),
            'cloudinary_public_id' => $validated['cloudinary_public_id'],
            'url' => $validated['url'],
            'thumbnail_url' => $validated['thumbnail_url'] ?? $validated['url'],
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'album' => $validated['album'] ?? null,
            'file_size' => $validated['file_size'] ?? 0,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'format' => $validated['format'] ?? null,
        ]);

        // Toujours retourner JSON - le frontend gérera le refresh
        return response()->json([
            'success' => true,
            'media' => $media->load('uploader:id,first_name,last_name'),
        ]);
    }

    // Mettre à jour un média (titre, album, description)
    public function update(Request $request, Formation $formation, Media $media): JsonResponse
    {
        $this->authorize('update', $formation);

        if ($media->formation_id !== $formation->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'album' => 'nullable|string|max:255',
        ]);

        $media->update($validated);

        return response()->json([
            'success' => true,
            'media' => $media,
        ]);
    }

    // Mettre à jour plusieurs médias en masse (pour les albums)
    public function batchUpdate(Request $request, Formation $formation): JsonResponse
    {
        $this->authorize('update', $formation);

        $validated = $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'string|exists:medias,id',
            'album' => 'required|string|max:255',
        ]);

        // Mettre à jour tous les médias sélectionnés
        Media::where('formation_id', $formation->id)
            ->whereIn('id', $validated['media_ids'])
            ->update(['album' => $validated['album']]);

        return response()->json([
            'success' => true,
            'message' => count($validated['media_ids']) . ' média(s) ajouté(s) à l\'album "' . $validated['album'] . '"',
        ]);
    }

    // Supprimer un média
    public function destroy(Formation $formation, Media $media): JsonResponse
    {
        $this->authorize('update', $formation);

        if ($media->formation_id !== $formation->id) {
            abort(403);
        }

        // Supprimer sur Cloudinary
        $resourceType = $media->type === 'video' ? 'video' : 'image';
        $deleted = $this->cloudinaryService->delete($media->cloudinary_public_id, $resourceType);

        if (!$deleted) {
            \Log::warning("Failed to delete from Cloudinary: {$media->cloudinary_public_id}");
        }

        // Supprimer en BDD
        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Média supprimé avec succès',
        ]);
    }

    // Télécharger un média
    public function download(Formation $formation, Media $media): StreamedResponse
    {
        $this->authorize('view', $formation);

        if ($media->formation_id !== $formation->id) {
            abort(403);
        }

        $filename = $media->title 
            ? $media->title . '.' . $media->format 
            : basename($media->cloudinary_public_id) . '.' . $media->format;

        return response()->streamDownload(function () use ($media) {
            $content = file_get_contents($media->url);
            echo $content;
        }, $filename, [
            'Content-Type' => $media->type === 'video' ? 'video/mp4' : 'image/' . ($media->format ?? 'jpeg'),
        ]);
    }

    // Récupérer les stats Cloudinary (pour la jauge)
    public function cloudinaryStats(): JsonResponse
    {
        $usage = $this->cloudinaryService->getUsage();

        return response()->json($usage);
    }

    // Créer un ZIP d'un album complet
    public function downloadAlbum(Request $request, Formation $formation): StreamedResponse
    {
        $this->authorize('view', $formation);

        $album = $request->input('album');

        $query = Media::where('formation_id', $formation->id);
        
        if ($album && $album !== 'all') {
            $query->where('album', $album);
        }

        $medias = $query->get();

        if ($medias->isEmpty()) {
            abort(404, 'Aucun média trouvé');
        }

        $zipName = $album 
            ? "{$formation->name} - {$album}.zip" 
            : "{$formation->name} - Tous les médias.zip";

        $zipName = str_replace(['/', '\\'], '_', $zipName);

        return response()->streamDownload(function () use ($medias) {
            $zip = new \ZipStream\ZipStream(outputName: 'download.zip');

            foreach ($medias as $media) {
                $filename = $media->title 
                    ? $media->title . '.' . $media->format 
                    : basename($media->cloudinary_public_id) . '.' . $media->format;

                $content = file_get_contents($media->url);
                $zip->addFile($filename, $content);
            }

            $zip->finish();
        }, $zipName, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
