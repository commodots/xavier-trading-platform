<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\UserSession;
use App\Models\User;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function loginHistory(Request $request)
    {
        $query = LoginHistory::with('user');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by successful/failed
        if ($request->has('successful')) {
            $query->where('successful', $request->successful);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('logged_in_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('logged_in_at', '<=', $request->date_to);
        }

        // Search by IP or device
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhere('device', 'like', "%{$search}%")
                  ->orWhere('browser', 'like', "%{$search}%");
            });
        }

        $histories = $query->orderBy('logged_in_at', 'desc')->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $histories
        ]);
    }

    public function activeSessions(Request $request)
    {
        $query = UserSession::with('user');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->orderBy('last_activity', 'desc')->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    public function logoutSession($id)
    {
        $session = UserSession::findOrFail($id);
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session terminated successfully'
        ]);
    }

    public function logoutAllSessions($userId)
    {
        UserSession::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'All sessions terminated successfully'
        ]);
    }
}