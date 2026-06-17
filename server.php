<?php

$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$requested = $publicPath.$uri;

if ($uri !== '/' && file_exists($requested)) {
    // Serve the file directly (needed because PHP dev server resolves
    // return false from the router script against the working dir, not public/)
    // mime_content_type() misdetects common web assets (notably .js as
    // text/plain on Windows), which breaks strict MIME checking for ES
    // modules in the browser — map known extensions explicitly first.
    $extensionMimes = [
        'js'    => 'text/javascript',
        'mjs'   => 'text/javascript',
        'css'   => 'text/css',
        'json'  => 'application/json',
        'svg'   => 'image/svg+xml',
        'wasm'  => 'application/wasm',
        'map'   => 'application/json',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];
    $extension = strtolower(pathinfo($requested, PATHINFO_EXTENSION));
    $mime = $extensionMimes[$extension] ?? mime_content_type($requested) ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    readfile($requested);
    exit;
}

require_once $publicPath.'/index.php';
