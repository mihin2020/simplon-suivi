<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const IP_MAX_FAILURES = 30;

    private const IP_DECAY_SECONDS = 600;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey(), self::IP_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
            ]);
        }

        // Block inactive accounts after successful credential check
        if (! Auth::user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Ce compte a été désactivé. Contactez un administrateur.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        $isTooManyForAccount = RateLimiter::tooManyAttempts($this->throttleKey(), 5);
        $isTooManyForIp = RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::IP_MAX_FAILURES);

        if (! $isTooManyForAccount && ! $isTooManyForIp) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            $isTooManyForIp ? RateLimiter::availableIn($this->ipThrottleKey()) : 0,
        );

        throw ValidationException::withMessages([
            'email' => "Trop de tentatives de connexion. Réessayez dans {$seconds} secondes.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /** All the failures of one address, whatever the account: stops one address from trying many accounts. */
    private function ipThrottleKey(): string
    {
        return 'login-ip|'.$this->ip();
    }
}
