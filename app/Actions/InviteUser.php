<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Mail\UserInvitation;
use App\Models\ActivationToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class InviteUser
{
    public function execute(string $firstName, string $lastName, string $email, UserRole $role): User
    {
        [$user, $plainToken] = DB::transaction(function () use ($firstName, $lastName, $email, $role) {
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

            return [$user, $plainToken];
        });

        try {
            // Queued: avoids PHP max_execution_time fatal on slow/blocked SMTP from Railway.
            Mail::to($email)->queue(new UserInvitation($user, $plainToken));
        } catch (Throwable $e) {
            Log::error('Failed to queue invitation email', [
                'email' => $email,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $user;
    }
}
