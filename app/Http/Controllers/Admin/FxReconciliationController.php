<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FxReconciliationController extends Controller
{
    public function __construct() {}

    /**
     * Get reconciliation data - Admin only
     */
    public function getReconciliation(): JsonResponse
    {
        // Verify admin authorization
        if (! Auth::user() || ! Auth::user()->hasRole('admin')) {
            ActivityLog::log(
                Auth::id(),
                'fx_reconciliation_access_denied',
                ['endpoint' => 'getReconciliation']
            );

            return response()->json([
                'message' => 'Unauthorized access',
            ], 403);
        }

        try {
            $brokerBalance = 0;
            // Get user liabilities
            $usdWallets = Wallet::where('currency', 'USD')->get();

            $usersClearedBalance = $usdWallets->sum('usd_cleared');
            $usersUnclearedBalance = $usdWallets->sum('usd_uncleared');
            $usersLockedBalance = $usdWallets->sum('locked');

            $userLiability = $usersClearedBalance + $usersUnclearedBalance + $usersLockedBalance;

            // Calculate buffer/shortfall
            $buffer = $brokerBalance - $userLiability;

            // Get pending settlements
            $pendingSettlements = $usdWallets->where('usd_uncleared', '>', 0)->count();

            $dailyFxCount = 0;

            // Log successful access
            ActivityLog::log(
                Auth::id(),
                'fx_reconciliation_viewed',
                [
                    'broker_balance' => $brokerBalance,
                    'user_liability' => $userLiability,
                    'buffer' => $buffer,
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'broker_balance' => $brokerBalance,
                    'user_liability' => $userLiability,
                    'buffer' => $buffer,
                    'has_shortfall' => $buffer < 0,
                    'shortfall' => max(0, -$buffer),
                    'pending_settlements' => $pendingSettlements,
                    'broker_account_id' => null,
                    'users_cleared_balance' => $usersClearedBalance,
                    'users_uncleared_balance' => $usersUnclearedBalance,
                    'users_locked_balance' => $usersLockedBalance,
                    'reconciliation_date' => now()->toDateString(),
                    'last_synced' => now(),
                    'last_checked' => now(),
                    'daily_fx_count' => $dailyFxCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('FX reconciliation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            ActivityLog::log(
                Auth::id(),
                'fx_reconciliation_failed',
                ['error' => $e->getMessage()]
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch reconciliation data. Please try again.',
            ], 422);
        }
    }

    /**
     * Get recent FX transactions - Admin only
     */
    public function getRecentTransactions(): JsonResponse
    {
        // Verify admin authorization
        if (! Auth::user() || ! Auth::user()->hasRole('admin')) {
            ActivityLog::log(
                Auth::id(),
                'fx_transactions_access_denied',
                ['endpoint' => 'getRecentTransactions']
            );

            return response()->json([
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Get recent transactions (placeholder)
        $transactions = [];

        // Log access
        ActivityLog::log(
            Auth::id(),
            'fx_transactions_viewed',
            ['count' => count($transactions)]
        );

        return response()->json([
            'status' => 'success',
            'data' => $transactions,
        ]);
    }

    /**
     * Run reconciliation manually - Admin only
     */
    public function runReconciliation(): JsonResponse
    {
        // Verify admin authorization
        if (! Auth::user() || ! Auth::user()->hasRole('admin')) {
            ActivityLog::log(
                Auth::id(),
                'fx_reconciliation_run_denied',
                ['endpoint' => 'runReconciliation']
            );

            return response()->json([
                'message' => 'Unauthorized access',
            ], 403);
        }

        try {
            // Log the reconciliation run attempt
            ActivityLog::log(
                Auth::id(),
                'fx_reconciliation_run',
                []
            );

            // This could trigger the reconciliation logic
            // For now, just return updated data
            return $this->getReconciliation();
        } catch (\Exception $e) {
            // Log the error
            ActivityLog::log(
                Auth::id(),
                'fx_reconciliation_run_failed',
                ['error' => $e->getMessage()]
            );

            return response()->json([
                'status' => 'error',
                'message' => 'Reconciliation failed. Please check logs.',
            ], 422);
        }
    }

    /**
     * Get settlement pending wallets - Admin only
     */
    public function getPendingSettlements(): JsonResponse
    {
        // Verify admin authorization
        if (! Auth::user() || ! Auth::user()->hasRole('admin')) {
            ActivityLog::log(
                Auth::id(),
                'fx_pending_access_denied',
                ['endpoint' => 'getPendingSettlements']
            );

            return response()->json([
                'message' => 'Unauthorized access',
            ], 403);
        }

        $wallets = Wallet::with('user')
            ->where('currency', 'USD')
            ->where('usd_uncleared', '>', 0)
            ->get()
            ->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'user_email' => $wallet->user->email,
                    'uncleared_balance' => $wallet->usd_uncleared,
                    'cleared_balance' => $wallet->usd_cleared,
                    'created_at' => $wallet->created_at,
                ];
            });

        // Log access
        ActivityLog::log(
            Auth::id(),
            'fx_pending_settlements_viewed',
            ['count' => $wallets->count()]
        );

        return response()->json([
            'status' => 'success',
            'data' => $wallets,
        ]);
    }
}
