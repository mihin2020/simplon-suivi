<?php

namespace App\Http\Controllers;

use App\Http\Requests\Media\BatchUpdateMediaRequest;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Models\Formation;
use App\Models\FormationLink;
use App\Models\Media;
use App\Services\CloudinaryService;
use App\Services\Media\MediaFiles;
use App\Services\Media\MediaStorageUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(private readonly CloudinaryService $cloudinary) {}

    /** La médiathèque d'une formation. */
    public function index(Formation $formation, MediaStorageUsage $usage): Response
    {
        $this->authorize('view', $formation);

        return Inertia::render('Formations/MediaLibrary', [
            'formation' => $formation->load('project:id,name'),
            'medias' => Media::where('formation_id', $formation->id)
                ->with('uploader:id,first_name,last_name')
                ->orderBy('album')
                ->orderByDesc('created_at')
                ->get(),
            'albums' => Media::where('formation_id', $formation->id)
                ->whereNotNull('album')
                ->distinct()
                ->orderBy('album')
                ->pluck('album'),
            'links' => FormationLink::where('formation_id', $formation->id)
                ->with('creator:id,first_name,last_name')
                ->orderByDesc('created_at')
                ->get(),
            'cloudinaryUsage' => $usage->fromDatabase(),
            'cloudName' => config('cloudinary.cloud_name'),
            'uploadPreset' => config('cloudinary.upload_preset', 'simplon_medias'),
        ]);
    }

    /** Enregistre un média déjà envoyé sur Cloudinary par le navigateur. */
    public function store(StoreMediaRequest $request, Formation $formation): JsonResponse
    {
        $data = $request->validated();

        $media = Media::create([
            ...$data,
            'formation_id' => $formation->id,
            'uploaded_by' => $request->user()->id,
            'thumbnail_url' => $data['thumbnail_url'] ?? $data['url'],
            'file_size' => $data['file_size'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'media' => $media->load('uploader:id,first_name,last_name'),
        ]);
    }

    /** Titre, description et album d'un média. */
    public function update(UpdateMediaRequest $request, Formation $formation, Media $media): JsonResponse
    {
        $this->ensureBelongsTo($formation, $media);

        $media->update($request->validated());

        return response()->json(['success' => true, 'media' => $media]);
    }

    /** Range plusieurs médias de la formation dans un album. */
    public function batchUpdate(BatchUpdateMediaRequest $request, Formation $formation): JsonResponse
    {
        $data = $request->validated();

        Media::where('formation_id', $formation->id)
            ->whereIn('id', $data['media_ids'])
            ->update(['album' => $data['album']]);

        return response()->json([
            'success' => true,
            'message' => count($data['media_ids']).' média(s) ajouté(s) à l\'album "'.$data['album'].'"',
        ]);
    }

    public function destroy(Formation $formation, Media $media): JsonResponse
    {
        $this->authorize('update', $formation);
        $this->ensureBelongsTo($formation, $media);

        if (! $this->cloudinary->delete($media->cloudinary_public_id, $media->isVideo() ? 'video' : 'image')) {
            Log::warning("Failed to delete from Cloudinary: {$media->cloudinary_public_id}");
        }

        $media->delete();

        return response()->json(['success' => true, 'message' => 'Média supprimé avec succès']);
    }

    public function download(Formation $formation, Media $media, MediaFiles $files): StreamedResponse
    {
        $this->authorize('view', $formation);
        $this->ensureBelongsTo($formation, $media);

        $content = $files->contents($media);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $media->downloadName(), [
            'Content-Type' => $media->isVideo() ? 'video/mp4' : 'image/'.($media->format ?? 'jpeg'),
        ]);
    }

    /** La jauge de stockage lue directement chez Cloudinary. */
    public function cloudinaryStats(): JsonResponse
    {
        return response()->json($this->cloudinary->getUsage());
    }

    /** Un ZIP d'un album, ou de tous les médias de la formation. */
    public function downloadAlbum(Request $request, Formation $formation, MediaFiles $files): StreamedResponse
    {
        $this->authorize('view', $formation);

        $album = $request->input('album');
        $medias = Media::where('formation_id', $formation->id)->inAlbum($album)->get();

        abort_if($medias->isEmpty(), 404, 'Aucun média trouvé');

        $zipName = str_replace(['/', '\\'], '_', $album
            ? "{$formation->name} - {$album}.zip"
            : "{$formation->name} - Tous les médias.zip");

        return response()->streamDownload(fn () => $files->streamZip($medias), $zipName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /** A media is reached through its own formation only. */
    private function ensureBelongsTo(Formation $formation, Media $media): void
    {
        abort_if($media->formation_id !== $formation->id, 403);
    }
}
