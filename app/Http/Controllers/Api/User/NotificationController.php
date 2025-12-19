<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationPreference;

class NotificationController extends Controller
{
    /**
     * Get user notification settings
     */
    public function show()
    {
        $user = Auth::user();
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            ['email' => true, 'sms' => true, 'push' => true] 
        );

        return response()->json([
            'success' => true,
            'data' => $prefs
        ]);
    }

    /**
     * Update notification settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'email' => 'required|boolean',
            'sms' => 'required|boolean',
            'push' => 'required|boolean',
        ]);

        $user = Auth::user();
        $prefs = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['email', 'sms', 'push'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated!',
            'data' => $prefs
        ]);
    }
}