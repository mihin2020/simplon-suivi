<?php

namespace App\Support;

/**
 * Absolute links for e-mails, built from APP_URL and never from the Host header of the request: a forged Host header
 * would otherwise put the attacker's domain in a password reset or an invitation link.
 */
class AppUrl
{
    public static function to(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
