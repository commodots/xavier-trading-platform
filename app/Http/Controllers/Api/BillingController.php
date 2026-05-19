<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BillingRecord;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * Fetch user's financial dashboard context details
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'wallet_balance' => (float) $user->wallet_balance,
                'wallet_debt' => (float) $user->wallet_debt,
                'subscription_status' => $user->subscription_status,
                'current_tier' => $user->current_tier,
                'next_fee_due_at' => $user->next_fee_due_at ? $user->next_fee_due_at->toIso8601String() : null,
            ]
        ]);
    }

    /**
     * Get historical billing statements/ledgers
     */
    public function statements(Request $request)
    {
        $records = BillingRecord::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * Settle accrued workspace/subscription debt using wallet funds
     */
    public function settleDebt(Request $request)
    {
        $user = $request->user();

        if ($user->wallet_debt <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has no outstanding debt balance.'
            ], 400);
        }

        if ($user->wallet_balance < $user->wallet_debt) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance to clear total debt. Please fund your wallet first.'
            ], 402);
        }

        DB::transaction(function () use ($user) {
            $debtPaid = $user->wallet_debt;

            // Deduct funds and reset structural parameters safely
            $user->decrement('wallet_balance', $debtPaid);
            $user->update([
                'wallet_debt' => 0,
                'subscription_status' => 'active' // Restore visibility access instantly
            ]);

            // Append auditing historical record row
            BillingRecord::create([
                'user_id' => $user->id,
                'amount' => $debtPaid,
                'type' => 'wallet_topup',
                'status' => 'paid',
                'reference' => 'DEBT_CLEAR_' . uniqid()
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Debt cleared successfully. Full account access has been restored.'
        ]);
    }

    /**
     * Upgrade or Renew Subscription tier instantly using wallet balances
     */
    public function purchasePlan(Request $request)
    {
        $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id'
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($request->subscription_plan_id);

        if ($user->wallet_balance < $plan->price) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance to complete plan purchase.'
            ], 402);
        }

        DB::transaction(function () use ($user, $plan) {
            // Deduct subscription cost
            $user->decrement('wallet_balance', $plan->price);

            // Terminate existing active subscription blocks gracefully
            $user->subscriptions()
                ->whereIn('status', ['active', 'trial'])
                ->update(['status' => 'expired']);

            // Build out clean subscription block instance
            $user->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'expires_at' => now()->addMonth(),
                'status' => 'active'
            ]);

            // Synchronize primary user attributes
            $user->update([
                'subscription_status' => 'active',
                'next_fee_due_at' => now()->addMonth(),
                'last_fee_charged_at' => now()
            ]);

            // Audit ledger tracking trail entry
            BillingRecord::create([
                'user_id' => $user->id,
                'amount' => $plan->price,
                'type' => 'subscription_fee',
                'status' => 'paid',
                'reference' => 'SUB_BUY_' . uniqid()
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Successfully subscribed to the {$plan->name} tier."
        ]);
    }
}