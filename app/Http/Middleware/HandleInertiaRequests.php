<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = "app";

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing('permissions');

        return [
            ...parent::share($request),
            "auth" => [
                "user" => $user
                    ? [
                        "id" => $user->id,
                        "full_name" => $user->full_name,
                        "email" => $user->email,
                        "role" => $user->role->value,
                        "role_label" => $user->role->label(),
                        "is_super_admin" => $user->isSuperAdmin(),
                        "permissions" => $user->isSuperAdmin()
                            ? null
                            : $user->permissions->pluck("slug")->values(),
                    ]
                    : null,
            ],
            "unread_notifications_count" => $request->user()
                ? $request->user()->notifications()->unread()->count()
                : 0,
            "unread_emails_count" => $request->user()
                ? \App\Models\Email::where("direction", "received")
                    ->where("is_archived", false)
                    ->where("is_read", false)
                    ->distinct("thread_id")
                    ->count("thread_id")
                : 0,
            "flash" => [
                "success" => $request->session()->get("success"),
                "warning" => $request->session()->get("warning"),
                "error" => $request->session()->get("error"),
            ],
        ];
    }
}
