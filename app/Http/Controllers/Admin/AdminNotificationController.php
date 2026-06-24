<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminNotificationLog;
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    public function index()
    {
        $logs = AdminNotificationLog::latest()->limit(20)->get();

        return response()->json([
            'success' => true,
            'data'    => $logs
        ]);
    }
    
    public function searchUsers(Request $request)
    {
        $query = User::query();

        $search = $request->query('search');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Process KYC conditions to match selection
        $kycStatus = $request->query('kyc_status');
        if (!empty($kycStatus)) {
            $query->where('kyc_status', $kycStatus);
        }

        $users = $query->limit(50)->get(['id', 'name', 'email', 'kyc_status', 'created_at']);

        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'user_ids'     => 'required|array|min:1',
            'user_ids.*'   => 'exists:users,id',
            'title'        => 'required|string|max:255',
            'message'      => 'required|string',
            'type'         => 'nullable|string|max:50',
            'send_email'   => 'required|boolean',
            'send_message' => 'required|boolean',
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();

        if ($users->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No valid recipients selected'], 422);
        }

        if (! $validated['send_email'] && ! $validated['send_message']) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose at least one delivery channel.',
            ], 422);
        }

        $type = $validated['type'] ?? 'info';

        Notification::send($users, new AdminBroadcastNotification(
            $validated['title'],
            $validated['message'],
            $validated['send_email'],
            $validated['send_message'],
            $type
        ));

        AdminNotificationLog::create([
            'title'           => $validated['title'],
            'message'         => $validated['message'],
            'type'            => $type,
            'recipient_count' => $users->count(),
            'sent_email'      => $validated['send_email'],
            'sent_message'    => $validated['send_message'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification dispatch initiated successfully.'
        ]);
    }
}