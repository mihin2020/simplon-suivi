<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email'    => 'L\'adresse email n\'est pas valide.',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            ActivationToken::where('user_id', $user->id)
                ->where('type', 'password_reset')
                ->whereNull('used_at')
                ->delete();

            $plainToken = Str::random(64);
            $hashedToken = hash('sha256', $plainToken);

            ActivationToken::create([
                'user_id'    => $user->id,
                'token'      => $hashedToken,
                'type'       => 'password_reset',
                'expires_at' => now()->addHour(),
            ]);

            $resetUrl = url('/reinitialisation/' . $plainToken);

            Mail::send([], [], function ($message) use ($user, $resetUrl) {
                $message->to($user->email)
                    ->subject('Réinitialisation de votre mot de passe — Simplon BF')
                    ->html(
                        '<div style="font-family:sans-serif;max-width:520px;margin:0 auto;">'
                        . '<h2 style="color:#1F3A4D;">Réinitialisation de mot de passe</h2>'
                        . '<p>Bonjour <strong>' . e($user->full_name) . '</strong>,</p>'
                        . '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>'
                        . '<p>Cliquez sur le bouton ci-dessous (lien valable <strong>1 heure</strong>) :</p>'
                        . '<p style="text-align:center;margin:2rem 0;">'
                        . '<a href="' . $resetUrl . '" style="background:#E5004C;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Réinitialiser mon mot de passe</a>'
                        . '</p>'
                        . '<p style="color:#6b7280;font-size:13px;">Si vous n\'avez pas fait cette demande, ignorez cet email.</p>'
                        . '</div>'
                    );
            });
        }

        return back()->with('success', 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.');
    }
}
