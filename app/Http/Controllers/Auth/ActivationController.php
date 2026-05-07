<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $activation = $this->findValid($token);

        if (! $activation) {
            return redirect()->route('login')
                ->with('error', 'Ce lien d\'activation est invalide ou expiré.');
        }

        return Inertia::render('Auth/Activate', [
            'token' => $token,
            'user'  => [
                'full_name' => $activation->user->full_name,
                'email'     => $activation->user->email,
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $activation = $this->findValid($token);

        if (! $activation) {
            return redirect()->route('login')
                ->with('error', 'Ce lien d\'activation est invalide ou expiré.');
        }

        $user = $activation->user;

        $user->update([
            'password'  => Hash::make($request->password),
            'is_active' => true,
        ]);

        $activation->update(['used_at' => now()]);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Votre compte a été activé. Bienvenue sur Simplon BF !');
    }

    private function findValid(string $token): ?ActivationToken
    {
        return ActivationToken::where('token', hash('sha256', $token))
            ->where('type', 'activation')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();
    }
}
