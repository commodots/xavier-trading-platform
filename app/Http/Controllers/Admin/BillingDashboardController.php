<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingRecord;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class BillingDashboardController extends Controller
{
    /**
     * Billing summary: total users, active subscription, trial, debt.
     */
    public function summary()
    {
        $totalUsers = User::count();
        $activeSubscriptionUsers = User::where('subscription_status', 'active')->count();
        $trialUsers = User::where('subscription_status', 'trial')->count();
        $debtTotal = User::where('wallet_debt', '>', 0)->sum('wallet_debt');

        return response()->json([
            'total_users'              => $totalUsers,
            'active_subscription_users' => $activeSubscriptionUsers,
            'trial_users'              => $trialUsers,
            'debt_total'               => $debtTotal,
        ]);
    }

    /**
     * Paginated user list filtered by subscription type.
     * type: 'all' | 'active' | 'trial'
     */
    public function users(Request $request)
    {
        $type = $request->get('type', 'all');
        $perPage = $request->get('per_page', 20);

        $query = User::with('subscriptions.plan')
            ->select('id', 'name', 'first_name', 'last_name', 'email', 'subscription_status', 'trial_ends_at', 'next_billing_date', 'wallet_debt', 'created_at');

        if ($type === 'active') {
            $query->where('subscription_status', 'active');
        } elseif ($type === 'trial') {
            $query->where('subscription_status', 'trial');
        }

        $users = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Upcoming renewals — users whose subscription or trial is expiring soon.
     */
    public function renewals(Request $request)
    {
        $users = User::with('subscriptions.plan')
            ->where(function ($q) {
                // Paying users with next_billing_date
                $q->where(function ($sub) {
                    $sub->where('subscription_status', 'active')
                        ->whereNotNull('next_billing_date');
                });
                // Trial users whose trial is still active (expires_at > now)
                $q->orWhere(function ($sub) {
                    $sub->where('subscription_status', 'trial')
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '>', now());
                });
                // Any user with an active UserSubscription record expiring soon
                $q->orWhereHas('subscriptions', function ($subQ) {
                    $subQ->where('status', 'active')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '>', now());
                });
            })
            ->orderBy('next_billing_date', 'asc')
            ->orderBy('trial_ends_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($users);
    }

    /**
     * Outstanding debt — users with wallet_debt > 0.
     */
    public function debts(Request $request)
    {
        $users = User::where('wallet_debt', '>', 0)
            ->orderByDesc('wallet_debt')
            ->paginate($request->get('per_page', 20));

        return response()->json($users);
    }

    /**
     * Revenue endpoint — total, today's, and monthly revenue.
     */
    public function revenue()
    {
        $total = BillingRecord::sum('amount');
        $today = BillingRecord::whereDate('created_at', today())->sum('amount');
        $monthly = BillingRecord::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return response()->json([
            'total'   => $total,
            'today'   => $today,
            'monthly' => $monthly,
        ]);
    }
}