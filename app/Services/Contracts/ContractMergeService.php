<?php

namespace App\Services\Contracts;

/**
 * Deterministic replacement of {{ key }} tokens in a template body.
 * No AI involved - merging must be predictable and reproducible.
 */
class ContractMergeService
{
    /**
     * Replace every {{ key }} token found in $html with its value from $values.
     * Unknown tokens are left untouched so a missing variable stays visible.
     *
     * @param  array<string, string>  $values
     */
    public function merge(?string $html, array $values): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($values): string {
                $key = $matches[1];

                return array_key_exists($key, $values)
                    ? e($values[$key])
                    : $matches[0];
            },
            $html
        ) ?? $html;
    }

    /**
     * Extract the distinct variable keys referenced in an HTML body.
     *
     * @return list<string>
     */
    public function extractKeys(?string $html): array
    {
        if ($html === null || $html === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $html, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
