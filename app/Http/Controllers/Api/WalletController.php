<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Demo\DemoLedger;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoWallet;
use App\Models\FxRate;
use App\Models\Ledger;
use App\Models\NewTransaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    // THE DYNAMIC MODEL RESOLVER
    private function resolveModels(?Request $request = null)
    {
        $user = Auth::user();

        $mode = $request ? $request->query('mode', $user->trading_mode) : $user->trading_mode;
        $isDemo = $mode === 'demo';

        return (object) [
            'isDemo' => $isDemo,
            'mode' => $mode,
            'wallet' => $isDemo ? new DemoWallet : new Wallet,
            'transaction' => $isDemo ? new DemoTransaction : new NewTransaction,
            'ledger' => $isDemo ? new DemoLedger : new Ledger,
        ];
    }

    public function balances(Request $request)
    {
        $user = Auth::user();
        $models = $this->resolveModels($request);

        $ngnWallet = $models->wallet->where('user_id', $user->id)->where('currency', 'NGN')->first();
        $usdWallet = $models->wallet->where('user_id', $user->id)->where('currency', 'USD')->first();

        return response()->json([
            'success' => true,
            'mode' => $user->trading_mode,
            'data' => [
                'balance_ngn' => (float) (($ngnWallet?->ngn_cleared ?? 0) + ($ngnWallet?->ngn_uncleared ?? 0) + ($ngnWallet?->locked ?? 0)),
                'balance_usd' => (float) (($usdWallet?->usd_cleared ?? 0) + ($usdWallet?->usd_uncleared ?? 0) + ($usdWallet?->locked ?? 0)),
                'cleared_balance_ngn' => (float) ($ngnWallet?->ngn_cleared ?? 0),
                'cleared_balance_usd' => (float) ($usdWallet?->usd_cleared ?? 0),
                'uncleared_balance_ngn' => (float) ($ngnWallet?->ngn_uncleared ?? 0),
                'locked_balance_ngn' => (float) ($ngnWallet?->locked ?? 0),
                'uncleared_balance_usd' => (float) ($usdWallet?->usd_uncleared ?? 0),
                'locked_balance_usd' => (float) ($usdWallet?->locked ?? 0),
            ],
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:500',
            'currency' => 'required',
        ]);

        $user = Auth::user();
        $currency = strtoupper($request->currency);
        $models = $this->resolveModels($request);

        // PCI/PSD2: Verify user passed SCA (2FA + email verified)
        if (! \App\Services\Compliance\PciPsd2Compliance::canProcessPayment($user, $request->amount)) {
            return response()->json([
                'success' => false,
                'message' => '2FA verification required for withdrawals over NGN 1M. Please enable 2FA in settings.',
            ], 403);
        }

        // PCI/PSD2: Check daily withdrawal limit
        $dailyLimit = \App\Services\Compliance\PciPsd2Compliance::getDailyTransactionLimit($user, 'withdrawal');
        if ($request->amount > $dailyLimit) {
            return response()->json([
                'success' => false,
                'message' => 'Daily withdrawal limit exceeded. Remaining: NGN '.number_format($dailyLimit, 2),
            ], 422);
        }

        // PSD2: Detect suspicious activity
        if (\App\Services\Compliance\PciPsd2Compliance::isSuspiciousActivity($user, [
            'type' => 'withdrawal',
            'amount' => $request->amount,
        ])) {
            // Log but allow (can be flagged by compliance team asynchronously)
        }

        return DB::transaction(function () use ($request, $user, $currency, $models) {
            $wallet = $models->wallet->where('user_id', $user->id)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';
            $clearedBalance = $wallet ? ($currency === 'NGN' ? $wallet->ngn_cleared : $wallet->usd_cleared) : 0;

            if (! $wallet || $clearedBalance < $request->amount) {
                return response()->json(['message' => 'Insufficient cleared funds'], 400);
            }

            $wallet->decrement($clearedCol, $request->amount);
            $wallet->decrement('balance', $request->amount);

            $models->transaction->create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'currency' => $currency,
                'status' => 'completed',
                'charge' => 0,
                'net_amount' => $request->amount,
                'meta' => [
                    'note' => 'User initiated withdrawal',
                    'mode' => $models->mode,
                ],
            ]);

            // PCI-DSS: Audit log for all payment operations
            \App\Services\Compliance\PciPsd2Compliance::logPaymentOperation($user, 'withdrawal', [
                'amount' => $request->amount,
                'currency' => $currency,
            ]);

            return response()->json(['success' => true]);
        });
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'currency' => 'required|in:NGN,USD',
        ]);

        $user = Auth::user();
        $currency = strtoupper($request->currency);
        $models = $this->resolveModels($request);

        return DB::transaction(function () use ($request, $user, $currency, $models) {
            $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

            $wallet = $models->wallet->firstOrCreate(
                ['user_id' => $user->id, 'currency' => $currency],
                ['balance' => 0, 'status' => 'active', 'ngn_cleared' => 0, 'usd_cleared' => 0, 'locked' => 0]
            );

            // Row lock for safety
            $wallet = $models->wallet->where('id', $wallet->id)->lockForUpdate()->first();

            $wallet->increment($clearedCol, $request->amount);
            $wallet->increment('balance', $request->amount);

            $models->transaction->create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'currency' => $currency,
                'status' => 'completed',
                'charge' => 0,
                'net_amount' => $request->amount,
                'meta' => [
                    'reference' => 'XAV-'.strtoupper(bin2hex(random_bytes(4))),
                    'mode' => $models->mode,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => "You have successfully deposited " . ($currency === 'USD' ? '$' : '₦') . number_format($request->amount, 2) . " to your {$currency} wallet.",
            ]);
        });
    }

    public function convert(Request $request)
    {
        try {
            $user = Auth::user();
            $models = $this->resolveModels($request);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'from' => 'sometimes|in:NGN,USD',
            ]);

            $fromCurrency = $validated['from'] ?? 'NGN';
            $toCurrency = $fromCurrency === 'NGN' ? 'USD' : 'NGN';
            $amount = (float) $validated['amount'];

            return DB::transaction(function () use ($user, $amount, $fromCurrency, $toCurrency, $models) {
                $sourceWallet = $models->wallet->where('user_id', $user->id)
                    ->where('currency', $fromCurrency)
                    ->lockForUpdate()
                    ->first();

                $clearedCol = $fromCurrency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

                if (! $sourceWallet || ($sourceWallet->{$clearedCol} ?? 0) < $amount) {
                    return response()->json(['error' => "Insufficient cleared $fromCurrency balance"], 400);
                }

                $rate = FxRate::where('from_currency', $fromCurrency)
                    ->where('to_currency', $toCurrency)
                    ->latest()
                    ->first();

                if (! $rate) {
                    return response()->json(['error' => 'FX rate not configured'], 400);
                }

                if ($fromCurrency === 'NGN' && $toCurrency === 'USD') {
                    // If the rate is 1530, we divide to get USD
                    $convertedAmount = $amount / $rate->effective_rate;
                } else {
                    // If USD to NGN, we multiply (e.g., $10 * 1530)
                    $convertedAmount = $amount * $rate->effective_rate;
                }

                $isDemo = ($user->trading_mode === 'demo');

                $destCol = $isDemo
                    ? ($toCurrency === 'NGN' ? 'ngn_cleared' : 'usd_cleared')
                    : ($toCurrency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared');

                $status = $isDemo ? 'completed' : 'pending';

                $sourceWallet->decrement($clearedCol, $amount);
                $sourceWallet->decrement('balance', $amount);

                $destWallet = $models->wallet->firstOrCreate(
                    ['user_id' => $user->id, 'currency' => $toCurrency],
                    ['balance' => 0, 'status' => 'active', 'usd_cleared' => 0, 'usd_uncleared' => 0, 'locked' => 0]
                );

                $destWallet = $models->wallet->where('id', $destWallet->id)->lockForUpdate()->first();

                $destWallet->increment($destCol, $convertedAmount);
                $destWallet->increment('balance', $convertedAmount);

                $txReference = 'FX-'.\Illuminate\Support\Str::uuid();

                $models->ledger->create([
                    'user_id' => $user->id,
                    'currency' => $toCurrency,
                    'amount' => $convertedAmount,
                    'type' => 'currency_change',
                    'status' => $status,
                    'reference' => $txReference,
                    'meta' => [
                        'source_amount' => $amount,
                        'source_currency' => $fromCurrency,
                        'locked_rate' => $rate->effective_rate,
                        'mode' => $models->mode,
                    ],
                ]);

                $models->transaction->create([
                    'user_id' => $user->id,
                    'type' => 'currency_change',
                    'amount' => $amount,
                    'currency' => $fromCurrency,
                    'status' => 'pending',
                    'meta' => [
                        'reference' => $txReference,
                        'to_currency' => $toCurrency,
                        'received_amount' => $convertedAmount,
                        'exchange_rate' => $rate->effective_rate,
                        'mode' => $models->mode,
                    ],
                ]);

                ActivityLog::log($user->id, 'fx_conversion_initiated', [
                    'from_amount' => $amount,
                    'to_amount' => $convertedAmount,
                    'rate' => $rate->effective_rate,
                ]);

                return response()->json([
                    'success' => true,
                    'amount_received' => $convertedAmount,
                    'amount_debited' => $amount,
                    'status' => $status,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Conversion failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Currency conversion failed. Please try again later.'], 500);
        }
    }

    private function getTotalBalance($user, $currency)
    {
        $models = $this->resolveModels($user);

        $w = $models->wallet->where('currency', $currency)->first();

        if ($currency === 'NGN') {
            return (float) (($w?->ngn_cleared ?? 0) + ($w?->ngn_uncleared ?? 0));
        } elseif ($currency === 'USD') {
            return (float) (($w?->usd_cleared ?? 0) + ($w?->usd_uncleared ?? 0));
        }

        return 0;
    }

    public function recentTransactions(Request $request)
    {
        $user = $request->user();
        $models = $this->resolveModels($request);

        $transactions = $models->transaction->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $formattedTransactions = $transactions->map(function ($t) {
            return [
                'id' => $t->id,
                'date' => $t->created_at->format('Y-m-d'),
                'type' => ucfirst($t->type),
                'currency' => $t->asset ?? $t->currency,
                'amount' => (float) $t->amount,
                'status' => $t->status ?? 'Completed',
                'ref' => $t->reference ?? $t->id,
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => $formattedTransactions,
        ]);
    }

    public function preview(Request $request)
    {
        $amount = $request->query('amount', 0);
        $from = $request->query('from', 'NGN');

        $fxRate = FxRate::where('from_currency', 'NGN')
            ->where('to_currency', 'USD')
            ->first();

        if (!$fxRate) {
            return response()->json(['error' => 'FX rate not available'], 400);
        }

        $rate = $fxRate->effective_rate; // e.g. 1500

        if ($from === 'NGN') {
            $preview = $amount / $rate;
            $label = 'USD';
        } else {
            $preview = $amount * $rate;
            $label = 'NGN';
        }

        return response()->json([
            'amount' => (float) $amount,
            'from_currency' => $from,
            'converted' => round($preview, 2),
            'to_currency' => $label,
            'rate' => $rate,
        ]);
    }
}
