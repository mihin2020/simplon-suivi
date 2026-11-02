<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The app sits behind a reverse proxy (Railway, nginx...) that terminates TLS and sets X-Forwarded-*. Trusting
    | every client ('*') lets anyone forge their IP and so dodge the login rate limit: by default only the private
    | networks of the proxy are trusted. TRUSTED_PROXIES (comma-separated IPs/CIDRs, or '*' when the origin can only
    | be reached through the proxy) overrides it, e.g. for a proxy with a public address.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES')
        ? array_map('trim', explode(',', (string) env('TRUSTED_PROXIES')))
        : ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '100.64.0.0/10', 'fd00::/8'],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | enforce     the browser blocks what the policy forbids (default, production).
    | report-only the browser only logs violations in its console: use it to diagnose a page that
    |             misbehaves before deciding which source to add below.
    | off         no CSP header (emergency switch only).
    |
    */
    'csp_mode' => env('CSP_MODE', 'enforce'),

    /*
    | Every external origin the application really uses. Add a source here only after checking it in
    | the browser console (a blocked resource is reported as "Refused to load ... Content Security Policy").
    */
    'csp_sources' => [
        // Cloudinary upload widget: script, its iframe, uploads and delivery (images, videos).
        'cloudinary_scripts' => ['https://upload-widget.cloudinary.com', 'https://widget.cloudinary.com'],
        'cloudinary_any' => ['https://*.cloudinary.com'],

        // Google Fonts (Inter, Material Symbols): stylesheet + font files.
        'font_styles' => ['https://fonts.googleapis.com'],
        'font_files' => ['https://fonts.gstatic.com'],

        // QR codes of public forms are images, and downloading one is a fetch().
        'qr_service' => ['https://api.qrserver.com'],
    ],

];
