<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'full_name' => $request->user()->full_name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role->value,
                    'role_label' => $request->user()->role->label(),
                    'is_super_admin' => $request->user()->isSuperAdmin(),
                    'permissions' => $request->user()->isSuperAdmin()
                        ? null
                        : $request->user()->permissions->pluck('slug'),
                ] : null,
            ],
            'unread_notifications_count' => $request->user() ? $request->user()->notifications()->unread()->count() : 0,
            'flash' => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
