<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryPost;
use Illuminate\Http\Request;

class AdvisoryController extends Controller
{
    /**
     * Fetch free posts for non-subscribed users
     */
    public function freePosts()
    {
        $posts = AdvisoryPost::where('is_premium', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * Fetch premium insights for VIP subscribers
     */
    public function premiumPosts()
    {
        $posts = AdvisoryPost::where('is_premium', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $posts]);
    }
 public function activateTrial(Request $request) {
    $user = $request->user();
    
    //Check if user already used a trial
    if ($user->trial_started_at) {
        return response()->json(['success' => false, 'message' => 'Trial already used.'], 403);
    }

    // Fetch the duration from SystemSettings
    $settings = \App\Models\SystemSetting::first();
    $days = $settings ? $settings->trial_days : 3; // Fallback to 3 if setting is missing

    // Set the expiry based on the Admin's setting
    $user->trial_started_at = now();
    $user->trial_expires_at = now()->addDays($days);
    $user->save();

    return response()->json(['success' => true]);
}
}