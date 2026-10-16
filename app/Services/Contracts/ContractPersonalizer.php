<?php

namespace App\Services\Contracts;

use App\Enums\ContractTarget;
use Illuminate\Database\Eloquent\Model;

/**
 * Rewrites a contract written for one person so it reads for another: every
 * known piece of identity of the original recipient found in the text is
 * replaced by the new recipient's. Deterministic - no AI, nothing else in
 * the text is touched.
 */
class ContractPersonalizer
{
    /** Shorter values (initials, "M.") would match all over the text. */
    private const MIN_LENGTH = 3;

    private const BLANK = '………………';

    public function __construct(private readonly ContractVariableResolver $resolver) {}

    public function adapt(string $html, ContractTarget $target, ?Model $from, Model $to): string
    {
        // Without a known original recipient there is nothing to recognise in the text.
        if ($from === null) {
            return $html;
        }

        $source = $this->resolver->resolve($target, $from);
        $destination = $this->resolver->resolve($target, $to);
        $replacements = [];

        foreach ($source as $key => $value) {
            $value = trim($value);

            if ($key === 'today' || mb_strlen($value) < self::MIN_LENGTH) {
                continue;
            }

            $new = trim($destination[$key] ?? '');
            // The body is HTML: match and write the escaped form.
            $replacements[e($value)] = $new !== '' ? e($new) : self::BLANK;
        }

        // strtr() tries the longest keys first and never re-scans its own output,
        // so a full name is replaced before its first or last name alone.
        return $replacements === [] ? $html : strtr($html, $replacements);
    }
}
