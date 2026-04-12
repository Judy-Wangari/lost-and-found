<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // Get all notifications for logged in user
    public function index()
    {
        try {
            $notifications = Notification::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json($notifications, 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch notifications.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Mark a specific notification as read
    public function show(string $id)
    {
        try {
            $notification = Notification::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->is_read = true;
            $notification->save();

            return response()->json([
                'message' => 'Notification marked as read.',
                'notification' => $notification
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch notification.',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    // Mark all notifications as read
    public function markAllRead()
    {
        try {
            Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'message' => 'All notifications marked as read.'
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to mark notifications as read.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Get unread notifications count for bell badge
    public function unreadCount()
    {
        try {
            $count = Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count();

            return response()->json([
                'unread_count' => $count
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch unread count.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}