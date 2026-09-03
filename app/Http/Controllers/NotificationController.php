<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function recent()
    {
        $notifications = auth()->user()->notifications()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'type', 'title', 'message', 'data', 'read_at', 'created_at']);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function unreadCount()
    {
        return response()->json([
            'count' => auth()->user()->notifications()->unread()->count(),
        ]);
    }

    public function markAsRead(Notification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markAllAsRead()
    {
        auth()->user()->notifications()->unread()->update(['read_at' => now()]);
        return back()->with('success', 'Toutes les notifications sont marquées comme lues.');
    }
}
