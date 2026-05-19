<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryPost;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;

class AdvisoryController extends Controller
{
    /**
     * Unified method for fetching advisories with access control
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $tier = $request->query('tier');

        $query = AdvisoryPost::query();
        if ($tier) {
            $query->where('tier', $tier);
        }

        // Apply our strict model level visibility boundary
        $query->accessibleBy($user);

        $posts = $query->latest()->paginate(10);

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * Fetch regular posts for non-subscribed users
     */
    public function regularPosts()
    {
        $posts = AdvisoryPost::where('is_premium', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'content', 'market_type', 'recommendation', 'risk_level', 'created_at']);

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * Fetch premium insights for VIP subscribers exclusively
     */
    public function premiumPosts(Request $request)
    {
        $user = $request->user();

        // Security Gate: Block users who don't have a live premium tier or active trial
        if (!$user || ($user->current_tier !== 'premium' && !$user->on_trial)) {
            return response()->json([
                'success' => false, 
                'error' => 'Access Denied. Premium Subscription tier required.'
            ], 403);
        }

        $posts = AdvisoryPost::where('is_premium', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'content', 'market_type', 'recommendation', 'risk_level', 'created_at']);

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * Activate Free Trial Tier
     */
    public function activateTrial(Request $request)
    {
        $request->validate(['tier' => 'required|in:regular,premium']);
        $user = $request->user();

        // Fetch all trial history (Active, Expired, or Cancelled)
        $trialHistory = $user->subscriptions()->with('plan')
            ->whereIn('status', ['trial', 'expired', 'cancelled'])
            ->get();

        $hasUsedRegular = $trialHistory->contains(fn($s) => $s->plan?->tier === 'regular');
        $hasUsedVip = $trialHistory->contains(fn($s) => $s->plan?->tier === 'premium');

        // Hierarchy Gates
        if ($request->tier === 'regular') {
            if ($hasUsedRegular || $hasUsedVip) {
                return response()->json(['success' => false, 'message' => 'Regular trial already used or exceeded.'], 403);
            }
        }

        if ($request->tier === 'premium') {
            if ($hasUsedVip) {
                return response()->json(['success' => false, 'message' => 'VIP trial already used.'], 403);
            }

            // UPGRADE PATH: If currently on active 'regular' trial, gracefully expire it
            $user->subscriptions()
                ->where('status', 'trial')
                ->whereHas('plan', fn($q) => $q->where('tier', 'regular'))
                ->update(['status' => 'expired']);
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

        // Explicitly sync the parent user status tracking field
        $user->update(['subscription_status' => 'trial']);

        return response()->json(['success' => true]);
    }
}