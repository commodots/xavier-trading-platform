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
        $paginator = $request->user()->notifications()->paginate(10);

        
        $paginator->getCollection()->transform(function ($notification) {
            return [
                'id'      => $notification->id,
                'title'   => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'title'   => $notification->data['title'] ?? $notification->data['subject'] ?? 'Notification',
                'message' => $notification->data['message'] ?? $notification->data['body'] ?? $notification->data['content'] ?? '',
                'type'    => $notification->data['type'] ?? 'info',
                'action'  => $notification->data['action'] ?? null, // Fixed $n variable bug here
                'read_at' => $notification->read_at,
                'time'    => $notification->created_at->diffForHumans(), 
            ];
        });

        return response()->json([
            'success'       => true,
            'unread_count'  => $request->user()->unreadNotifications()->count(),
            
            'notifications' => $paginator->items(), 
            'meta'          => [
                'current_page' => $paginator->currentPage(),
                'has_more'     => $paginator->hasMorePages(),
            ]
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