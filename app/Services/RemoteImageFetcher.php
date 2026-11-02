<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;

/**
 * Downloads an image from a URL found in user-supplied data (Excel import).
 *
 * The server must not become a proxy to its own network: only public http(s) hosts on ports 80/443 are
 * reached, the resolved address is pinned for the request (no DNS rebinding), redirects are followed
 * manually and checked at each hop, and the body must really be an image.
 */
class RemoteImageFetcher
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_REDIRECTS = 3;

    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * @return array{body: string, extension: string}|null
     */
    public function fetch(string $url): ?array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->resolveTarget($url);

            if ($target === null) {
                return null;
            }

            $response = Http::timeout(20)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"],
                        CURLOPT_MAXFILESIZE => self::MAX_BYTES,
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    ],
                ])
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    return null;
                }

                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }

            return $response->successful() ? $this->asImage($response->body()) : null;
        }

        return null;
    }

    /**
     * @return array{body: string, extension: string}|null
     */
    private function asImage(string $body): ?array
    {
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($body);
        $extension = $info === false ? null : (self::EXTENSIONS[$info[2]] ?? null);

        return $extension === null ? null : ['body' => $body, 'extension' => $extension];
    }

    /**
     * @return array{host: string, port: int, ip: string}|null
     */
    private function resolveTarget(string $url): ?array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim(strtolower($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $allowPrivate = (bool) config('services.remote_images.allow_private_hosts');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        // A developer machine serves files from localhost on any port (php artisan serve, Docker, ...).
        if (! $allowPrivate && ! in_array($port, [80, 443], true)) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $allowPrivate && ! $this->isPublicAddress($address)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
