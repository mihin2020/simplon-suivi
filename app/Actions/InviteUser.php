<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Mail\UserInvitation;
use App\Models\ActivationToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteUser
{
    public function execute(string $firstName, string $lastName, string $email, UserRole $role): User
    {
        // Si un utilisateur supprimé avec cet email existe, le restaurer
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->restore();
            $user->update([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'role'       => $role,
                'is_active'  => false,
                'password'   => Hash::make(Str::random(32)),
            ]);
        } else {
            $user = User::create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'password'   => Hash::make(Str::random(32)),
                'role'       => $role,
                'is_active'  => false,
            ]);
        }

        $plainToken = Str::random(64);

        ActivationToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plainToken),
            'type'       => 'activation',
            'expires_at' => now()->addHours(72),
        ]);

        Mail::to($email)->send(new UserInvitation($user, $plainToken));

        return $user;
    }
}
