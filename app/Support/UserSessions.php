<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Ends the sessions of a user, so a stolen session does not survive a change of credentials. */
class UserSessions
{
    /**
     * Signs the user out everywhere, except from the session given (the one that just proved who they are).
     * Remember-me cookies are invalidated too. Only the database session driver can list sessions by user.
     */
    public static function revokeAll(User $user, ?string $exceptSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
