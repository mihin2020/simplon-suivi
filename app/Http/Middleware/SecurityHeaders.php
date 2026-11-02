<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

        // A CSP only governs documents: sending it with a PDF, an image or JSON would at best be noise
        // and can stop the browser's built-in PDF viewer.
        if ($this->isHtml($response)) {
            $this->applyContentSecurityPolicy($response);
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /** The Cloudinary widget offers the camera as an upload source and runs in its own iframe. */
    private function permissionsPolicy(): string
    {
        $widget = implode(' ', array_map(fn (string $url) => '"'.$url.'"', config('security.csp_sources.cloudinary_scripts')));

        return "camera=(self {$widget}), microphone=(self {$widget}), geolocation=()";
    }

    private function isHtml(Response $response): bool
    {
        return str_starts_with((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        $mode = config('security.csp_mode');

        if ($mode === 'off') {
            return;
        }

        $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $response->headers->set($header, $this->contentSecurityPolicy());
    }

    private function contentSecurityPolicy(): string
    {
        $sources = config('security.csp_sources');
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            // No inline script and no eval: the app is a compiled bundle (Vue runtime, no template compiler).
            'script-src' => ["'self'", ...$sources['cloudinary_scripts'], ...$dev['http']],
            // Vue and the Cloudinary widget set inline styles; scripts are the real injection risk.
            'style-src' => ["'self'", "'unsafe-inline'", ...$sources['font_styles'], ...$dev['http']],
            'font-src' => ["'self'", 'data:', ...$sources['font_files'], ...$dev['http']],
            // Flags, favicons, QR codes, uploaded photos and images written in interviews come from many hosts.
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'media-src' => ["'self'", 'blob:', 'data:', 'https:'],
            'connect-src' => ["'self'", ...$sources['qr_service'], ...$sources['cloudinary_any'], ...$dev['http'], ...$dev['ws']],
            'frame-src' => ["'self'", ...$sources['cloudinary_any']],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $values, string $name) => $name.' '.implode(' ', array_unique($values)))
            ->implode('; ');
    }

    /**
     * While `npm run dev` runs, scripts, styles and the hot-reload socket come from the Vite server.
     *
     * @return array{http: array<int, string>, ws: array<int, string>}
     */
    private function viteDevServer(): array
    {
        $none = ['http' => [], 'ws' => []];

        if (! Vite::isRunningHot()) {
            return $none;
        }

        $url = trim((string) @file_get_contents(Vite::hotFile()));
        $parts = parse_url($url);

        if (! isset($parts['host'])) {
            return $none;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $hosts = ['localhost', '127.0.0.1'];

        // A CSP source cannot hold an IPv6 literal such as [::1]: browsers drop it, which blanks the page when Vite
        // listens on IPv6. The wildcard host is used instead, on the dev server port only.
        $hosts[] = str_starts_with($parts['host'], '[') ? '*' : $parts['host'];
        $hosts = array_values(array_unique($hosts));

        return [
            'http' => array_map(fn (string $host) => "http://{$host}{$port}", $hosts),
            'ws' => array_map(fn (string $host) => "ws://{$host}{$port}", $hosts),
        ];
    }
}
