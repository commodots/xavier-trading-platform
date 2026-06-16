<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingRecord;
use App\Models\User;
use Illuminate\Http\Request;

class BillingDashboardController extends Controller
{
    /**
     * Billing summary: active, trial, paying users, total debt.
     */
    public function summary()
    {
        return response()->json([
            'active_users' => User::active()->count(),
            'trial_users' => User::where('subscription_status', 'trial')->count(),
            'paying_users' => User::where('subscription_status', 'active')->count(),
            'debt_total' => User::sum('wallet_debt'),
        ]);
    }

    /**
     * Upcoming renewals — users with next_billing_date.
     */
    public function renewals(Request $request)
    {
        $users = User::whereNotNull('next_billing_date')
            ->orderBy('next_billing_date')
            ->paginate($request->get('per_page', 20));

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
            'total' => $total,
            'today' => $today,
            'monthly' => $monthly,
        ]);
    }
}
