<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationToken;
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
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
