<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\ActivationToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteUser
{
    public function execute(string $firstName, string $lastName, string $email, UserRole $role): User
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => Hash::make(Str::random(32)), // placeholder jusqu'à activation
            'role'       => $role,
            'is_active'  => false,
        ]);

        $plainToken = Str::random(64);

        ActivationToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $plainToken),
            'type'       => 'activation',
            'expires_at' => now()->addHours(24),
        ]);

        Mail::to($email)->send(new \App\Mail\UserInvitation($user, $plainToken));

        return $user;
    }
}
