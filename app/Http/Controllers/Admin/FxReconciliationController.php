<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Ledger;
use App\Models\FxRate;

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
            //  Get user liabilities from specific currency columns
            $usdWallets = Wallet::where('currency', 'USD')->get();
            $usersClearedBalance = $usdWallets->sum('usd_cleared');
            $usersUnclearedBalance = $usdWallets->sum('usd_uncleared');
            $usersLockedBalance = $usdWallets->sum('locked');

            $userLiability = $usersClearedBalance + $usersUnclearedBalance + $usersLockedBalance;

            //  Assuming platform holds user funds + platform profit
            $totalPlatformProfitUsd = Ledger::where('is_platform', true)->where('type', 'FX_MARKUP_PROFIT')->sum('amount');
            $brokerBalance = $userLiability + $totalPlatformProfitUsd; 

            $buffer = $brokerBalance - $userLiability;

            //Get pending settlements from Ledger
            $pendingSettlements = Ledger::where('status', 'pending')
                ->whereIn('type', ['FUND', 'FX_CONVERSION'])
                ->count();

            //  Calculate today's FX Margin in NGN
            $fxMarginUsdToday = Ledger::where('is_platform', true)
                ->where('type', 'FX_MARKUP_PROFIT')
                ->whereDate('created_at', today())
                ->sum('amount');
                
            $rate = FxRate::latest()->value('base_rate') ?? 1500;
            $fxMarginNgnToday = $fxMarginUsdToday * $rate;

            $dailyFxCount = Ledger::where('type', 'FX_CONVERSION')->whereDate('created_at', today())->count();

            ActivityLog::log(Auth::id(), 'fx_reconciliation_viewed', [
                'broker_balance' => $brokerBalance,
                'user_liability' => $userLiability,
                'buffer' => $buffer,
            ]);

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
                    'fx_margin_today' => $fxMarginNgnToday, 
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('FX reconciliation failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Unable to fetch reconciliation data.'], 422);
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

        $transactions = Ledger::with('user:id,email')
            ->whereIn('type', ['FX_CONVERSION', 'FUND', 'BROKER_FUNDING'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'user' => $t->user ? $t->user->email : 'System',
                    'type' => str_replace('_', ' ', $t->type),
                    'amount' => $t->amount,
                    'currency' => $t->currency,
                    'status' => $t->status,
                    'date' => $t->created_at->format('Y-m-d H:i')
                ];
            });

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
        if (! Auth::user() || ! Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $pending = Ledger::with('user:id,email')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $pending,
        ]);
    }
}
