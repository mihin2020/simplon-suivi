<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Password policy (CLAUDE.md §8): 8 characters minimum, one uppercase letter, one digit, one special character. */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        $valid = mb_strlen($password) >= 8
            && preg_match('/\p{Lu}/u', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^\p{L}\d]/u', $password);

        if (! $valid) {
            $fail('Le mot de passe doit contenir au moins 8 caractères, dont une majuscule, un chiffre et un caractère spécial.');
        }
    }
}
