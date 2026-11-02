<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationToken;
use App\Rules\StrongPassword;
use App\Support\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $reset = $this->findValid($token);

        if (! $reset) {
            return redirect()->route('login')
                ->with('error', 'Ce lien de réinitialisation est invalide ou expiré.');
        }

        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $reset->user->email,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ], [
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
        ]);

        $reset = $this->findValid($token);

        if (! $reset) {
            return redirect()->route('login')
                ->with('error', 'Ce lien de réinitialisation est invalide ou expiré.');
        }

        $reset->user->update([
            'password' => Hash::make($request->password),
        ]);

        $reset->update(['used_at' => now()]);

        // Whoever held a stolen session of this account must lose it with the old password.
        UserSessions::revokeAll($reset->user);

        return redirect()->route('login')
            ->with('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
    }

    private function findValid(string $token): ?ActivationToken
    {
        return ActivationToken::where('token', hash('sha256', $token))
            ->where('type', 'password_reset')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();
    }
}
