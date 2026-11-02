<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Media files live on Cloudinary only. Anything else (file://, internal hosts, other sites) would let
 * the server fetch arbitrary resources when a media is downloaded.
 */
class CloudinaryUrl implements ValidationRule
{
    private const HOST = 'res.cloudinary.com';

    public static function isValid(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && strtolower($parts['host'] ?? '') === self::HOST
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail("L'adresse du fichier doit provenir de Cloudinary (https://".self::HOST.').');
        }
    }
}
