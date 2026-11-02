<?php

return [

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
