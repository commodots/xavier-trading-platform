<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryPost;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;

class AdvisoryController extends Controller
{
    /**
     * Fetch regular posts for non-subscribed users
     */
    public function regularPosts()
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
    public function activateTrial(Request $request)
{
    $request->validate(['tier' => 'required|in:regular,premium']);
    $user = $request->user();

    // Fetch all trial history (Active, Expired, or Cancelled)
    $trialHistory = $user->subscriptions()->with('plan')
        ->whereIn('status', ['trial', 'expired', 'cancelled'])
        ->get();

    $hasUsedRegular = $trialHistory->contains(fn($s) => $s->plan->tier === 'regular');
    $hasUsedVip = $trialHistory->contains(fn($s) => $s->plan->tier === 'premium');

    // Hierarchy Gates
    if ($request->tier === 'regular') {
        // Cannot activate regular if they've used regular OR premium already
        if ($hasUsedRegular || $hasUsedVip) {
            return response()->json(['success' => false, 'message' => 'Regular trial already used or exceeded.'], 403);
        }
    }

    if ($request->tier === 'premium') {
        // Cannot activate premium trial if they've used pre,ium already
        if ($hasUsedVip) {
            return response()->json(['success' => false, 'message' => 'VIP trial already used.'], 403);
        }

        // UPGRADE PATH: If they are currently on an active 'regular' trial, expire it.
        $user->subscriptions()->where('status', 'trial')->update(['status' => 'expired']);
    }

    // Create the trial
    $plan = SubscriptionPlan::where('tier', $request->tier)->first();

        if (!$plan) {
            return response()->json(['success' => false, 'message' => "Subscription plan not found for tier: {$request->tier}"], 404);
        }

    $days = \App\Models\SystemSetting::value('trial_days') ?? 7;

    $user->subscriptions()->create([
        'subscription_plan_id' => $plan->id,
        'expires_at' => now()->addDays($days),
        'status' => 'trial',
    ]);

    return response()->json(['success' => true]);
}
}
