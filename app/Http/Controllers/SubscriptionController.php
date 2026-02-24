<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends Controller
{
    public function plans()
    {
        return response()->json(['success' => true, 'data' => SubscriptionPlan::all()]);
    }

   public function initializePayment(Request $request)
    {
        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $request->user()->email,
                'amount' => $plan->price * 100,
                'plan' => $plan->paystack_plan_code,
                'callback_url' => env('FRONTEND_URL') . '/advisory?plan_id=' . $plan->id, 
            ]);

        return $response->json();
    }

    public function verifyPayment(Request $request)
    {
        $reference = $request->reference;
        $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response['data']['status'] === 'success') {
            $plan = SubscriptionPlan::find($request->plan_id);

            UserSubscription::updateOrCreate(
                ['user_id' => auth()->id()],
                [
                    'subscription_plan_id' => $plan->id,
                    'expires_at' => now()->addDays($plan->duration_days),
                    // Paystack returns the authorization code for recurring subs here
                    'paystack_subscription_code' => $response['data']['authorization']['authorization_code'] ?? null
                ]
            );
            return response()->json(['success' => true, 'message' => 'Subscription Activated!']);
        }

        return response()->json(['success' => false, 'message' => 'Verification failed'], 400);
    }
    public function cancelSubscription(Request $request)
    {
        $user = $request->user();
        $localSub = UserSubscription::where('user_id', $user->id)->first();

        if (!$localSub) {
            return response()->json(['success' => false, 'message' => 'No active subscription found.'], 404);
        }

        // Ask Paystack for all subscriptions tied to this user's email
        $response = Http::withToken(env('PAYSTACK_SECRET_KEY'))
            ->get('https://api.paystack.co/subscription', [
                'customer' => $user->email
            ]);

        if ($response->successful()) {
            $paystackSubs = $response->json('data');
            
            //Find their active subscription in the Paystack array
            $activeSub = collect($paystackSubs)->firstWhere('status', 'active');

            if ($activeSub) {
                //Tell Paystack to permanently disable it
                Http::withToken(env('PAYSTACK_SECRET_KEY'))
                    ->post('https://api.paystack.co/subscription/disable', [
                        'code' => $activeSub['subscription_code'],
                        'token' => $activeSub['email_token']
                    ]);
            }
        }

        // Finally, wipe it from our local database so the paywall drops back down
        $localSub->delete();

        return response()->json(['success' => true, 'message' => 'Subscription cancelled successfully.']);
    }
}