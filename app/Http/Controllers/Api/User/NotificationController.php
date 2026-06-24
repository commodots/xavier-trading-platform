<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Use the custom Notification model to access all columns
        $paginator = Notification::where('notifiable_type', get_class($request->user()))
            ->where('notifiable_id', $request->user()->getKey())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $paginator->getCollection()->transform(function ($notification) {
            // Decode the data JSON string to array
            $data = is_string($notification->data) 
                ? json_decode($notification->data, true) 
                : $notification->data;
            
            // If data is still not an array, use empty array
            if (!is_array($data)) {
                $data = [];
            }

            
            $type = $data['type'] ?? 'info';
            if ($type === 'info' && str_contains($notification->type, 'AdminBroadcast')) {
                $type = 'broadcast';
            }

            
            if (str_contains($notification->type, 'AdminBroadcast') && empty($data)) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title ?? 'Notification',
                    'message' => $notification->message ?? '',
                    'type' => 'broadcast',
                    'action' => $notification->action ?? 'View Details',
                    'action_url' => $notification->action_url ?? null,
                    'read' => $notification->read_at !== null,
                    'time' => $notification->created_at->diffForHumans(),
                ];
            }

            return [
                'id' => $notification->id,
                
                'title' => $data['title'] ?? $data['subject'] ?? $notification->title ?? 'Notification',
                'message' => $data['message'] ?? $data['body'] ?? $data['content'] ?? $notification->message ?? '',
                'type' => $type,
                'action' => $data['action'] ?? $notification->action ?? null,
                'action_url' => $data['action_url'] ?? $notification->action_url ?? null,
                'read' => $notification->read_at !== null,
                'time' => $notification->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),

            'notifications' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
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
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Get user notification settings
     */
    public function showPreferences(Request $request)
    {
        $user = $request->user();
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email' => true,
                'sms' => true,
                'push' => true,
                'monthly_statements' => true,
                'newsletters' => false,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $prefs,
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

        $user = $request->user();
        $prefs = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['email', 'sms', 'push', 'monthly_statements', 'newsletters'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated!',
            'data' => $prefs,
        ]);
    }
}
