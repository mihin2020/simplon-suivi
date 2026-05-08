<?php

$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$requested = $publicPath.$uri;

if ($uri !== '/' && file_exists($requested)) {
    // Serve the file directly (needed because PHP dev server resolves
    // return false from the router script against the working dir, not public/)
    $mime = mime_content_type($requested) ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    readfile($requested);
    exit;
}

require_once $publicPath.'/index.php';
