<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function show()
    {
        // Find or create preferences so the tab isn't empty
        $prefs = NotificationPreference::firstOrCreate(
            ['user_id' => Auth::id()],
            ['email' => true, 'sms' => false, 'push' => true]
        );

        return response()->json(['success' => true, 'data' => $prefs]);
    }

    public function update(Request $request)
    {
        $prefs = NotificationPreference::where('user_id', Auth::id())->first();
        
        $prefs->update([
            'email' => $request->email,
            'sms' => $request->sms,
            'push' => $request->push,
        ]);

        return response()->json(['success' => true, 'data' => $prefs]);
    }
}