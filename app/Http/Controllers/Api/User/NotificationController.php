<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationPreference;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()->limit(50)->get()->map(fn ($n) => [
            'id'      => $n->id,
            'type'    => $n->data['type'] ?? 'info',
            'title'   => $n->data['title'] ?? 'Notification',
            'message' => $n->data['message'] ?? '',
            'action'  => $n->data['action'] ?? null,
            'read'    => !is_null($n->read_at),
            'time'    => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'success'      => true,
            'unread_count' => $user->unreadNotifications->count(),
            'notifications' => $notifications,
        ]);
    }
    public function markAsRead($id, Request $request)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['success' => true]);
    }
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
    /**
     * Get user notification settings
     */
    public function showPreferences()
    {
        $user = Auth::user();
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email' => true,
                'sms' => true,
                'push' => true,
                'monthly_statements' => true,
                'newsletters' => false
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $prefs
        ]);
    }

    /**
     * Update notification settings
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'email' => 'required|boolean',
            'sms' => 'required|boolean',
            'push' => 'required|boolean',
            'monthly_statements' => 'required|boolean',
            'newsletters' => 'required|boolean',
        ]);

        $user = Auth::user();
        $prefs = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['email', 'sms', 'push', 'monthly_statements', 'newsletters'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated!',
            'data' => $prefs
        ]);
    }
}
