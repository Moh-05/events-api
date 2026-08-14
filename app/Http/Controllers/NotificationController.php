<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // List the caller's notifications (newest first) + unread count
    public function index(Request $request)
    {
        [$type, $id] = $this->owner($request);

        $notifications = Notification::where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->latest()
            ->get();

        return response()->json([
            'status'        => 'success',
            'unread_count'  => $notifications->whereNull('read_at')->count(),
            'notifications' => $notifications,
        ]);
    }

    // Mark a single notification as read
    public function markAsRead(Request $request, $id)
    {
        [$type, $ownerId] = $this->owner($request);

        $notification = Notification::where('id', $id)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $ownerId)
            ->first();

        if (!$notification) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.notification_not_found'),
            ], 404);
        }

        $notification->update(['read_at' => now()]);

        return response()->json([
            'status'       => 'success',
            'notification' => $notification,
        ]);
    }

    // Mark all the caller's notifications as read
    public function markAllAsRead(Request $request)
    {
        [$type, $id] = $this->owner($request);

        Notification::where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.all_marked_read'),
        ]);
    }

    // Figure out whether the caller is a user or a vendor
    private function owner(Request $request): array
    {
        $actor = $request->user();
        $type  = $actor instanceof \App\Models\Vendor ? 'vendor' : 'user';

        return [$type, $actor->id];
    }
}
