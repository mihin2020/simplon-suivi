<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Rules\CloudinaryUrl;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use ZipStream\ZipStream;

/** Fetches media files from Cloudinary, one by one or as a ZIP archive. */
class MediaFiles
{
    private const TIMEOUT_SECONDS = 30;

    /**
     * Only files hosted on Cloudinary are fetched, never an arbitrary address stored in the table.
     *
     * @throws HttpException 422 for a foreign address
     * @throws RequestException when Cloudinary does not answer correctly
     */
    public function contents(Media $media): string
    {
        abort_unless(CloudinaryUrl::isValid($media->url), 422, 'Adresse de média non autorisée.');

        return Http::timeout(self::TIMEOUT_SECONDS)->get($media->url)->throw()->body();
    }

    /**
     * Writes a ZIP archive of the media to the output. A file that cannot be fetched is left out
     * (and logged) instead of breaking the whole archive.
     *
     * @param  Collection<int, Media>  $medias
     */
    public function streamZip(Collection $medias): void
    {
        $zip = new ZipStream(outputName: 'download.zip');

        foreach ($medias as $media) {
            try {
                $zip->addFile($media->downloadName(), $this->contents($media));
            } catch (Throwable $e) {
                Log::warning('Media left out of the archive', ['media_id' => $media->id, 'reason' => $e->getMessage()]);
            }
        }

        $zip->finish();
    }
}
