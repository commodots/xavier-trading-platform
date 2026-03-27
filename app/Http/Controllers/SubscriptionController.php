<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends Controller
{
    public function plans()
    {
        $days = \App\Models\SystemSetting::value('trial_days') ?? 7;

        return response()->json([
            'success' => true,
            'data' => SubscriptionPlan::all(),
            // Pass the setting here so the frontend can show "Start 7-Day Trial"
            'trial_settings' => [
                'days' => (int) $days,
            ],
        ]);
    }

    public function initializePayment(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $request->user()->email,
                'amount' => $plan->price * 100,
                'plan' => $plan->paystack_plan_code,
                'callback_url' => config('app.url').'/advisory?plan_id='.$plan->id,
            ]);

        return $response->json();
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required',
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $reference = $request->reference;
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response['data']['status'] === 'success') {
            $plan = SubscriptionPlan::find($request->plan_id);

            //  Verify the amount paid matches the plan price.
            // Paystack returns amount in kobo (subunits), plan price is in base units.
            $paidAmount = $response['data']['amount'] / 100;
            if ($paidAmount < $plan->price) {
                return response()->json(['success' => false, 'message' => 'Invalid payment amount for this plan.'], 400);
            }

            // Create a NEW record to preserve history
            $request->user()->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'starts_at' => now(),
                'expires_at' => now()->addDays($plan->duration_days),
                'status' => 'active',
                // Store the Subscription Code (SUB_...) for managing recurring billing, not the Auth Code.
                'paystack_subscription_code' => $response['data']['subscription_code'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Subscription Activated!']);
        }

        return response()->json(['success' => false, 'message' => 'Verification failed'], 400);
    }

    public function cancelSubscription(Request $request)
    {
        $user = $request->user();
        // Find the current, active subscription to cancel.
        $localSub = $user->subscriptions()
            ->whereIn('status', ['active', 'trial']) // Target active or trial
            ->latest()
            ->first();

        if (! $localSub) {
            return response()->json(['success' => false, 'message' => 'No active subscription found.'], 404);
        }

        // If the subscription was created via Paystack, we have a code to disable it.
        if ($localSub->paystack_subscription_code) {
            // To disable a subscription, we need both the code and an email token.
            // We can get the token by fetching the specific subscription from Paystack.
            $paystackSubResponse = Http::withToken(config('services.paystack.secret_key'))
                ->get("https://api.paystack.co/subscription/{$localSub->paystack_subscription_code}");

            if ($paystackSubResponse->successful()) {
                $paystackSubData = $paystackSubResponse->json('data');
                $emailToken = $paystackSubData['email_token'] ?? null;

                if ($emailToken) {
                    // Now, tell Paystack to permanently disable the correct subscription.
                    Http::withToken(config('services.paystack.secret_key'))
                        ->post('https://api.paystack.co/subscription/disable', [
                            'code' => $localSub->paystack_subscription_code,
                            'token' => $emailToken,
                        ]);
                }
            }
        }

        // Instead of deleting, we'll mark it as cancelled and expire it immediately.
        // This preserves the user's subscription history for analytics.
        $localSub->update([
            'status' => 'cancelled',
            'expires_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Subscription cancelled.']);
    }
}
