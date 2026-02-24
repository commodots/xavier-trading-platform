<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FxRate;
use App\Models\Ledger;
use App\Models\NewTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function balances()
    {
        $user = Auth::user();

        // Fetch specific rows
        $ngnWallet = $user->wallet()->where('currency', 'NGN')->first();
        $usdWallet = $user->wallet()->where('currency', 'USD')->first();

        return response()->json([
            'success' => true,
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
            'currency' => 'required'
        ]);
        
        $user = Auth::user();
        
        $wallet = \App\Models\Wallet::where('user_id', $user->id)
            ->where('currency', $request->currency)
            ->first();

        $clearedBalance = $request->currency === 'NGN' ? $wallet->ngn_cleared : $wallet->usd_cleared;

        if (!$wallet || $clearedBalance < $request->amount) {
            return response()->json(['message' => 'Insufficient cleared funds'], 400);
        }

        return DB::transaction(function () use ($request, $user) {
            
            $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

            DB::table('wallets')
                ->where('user_id', $user->id)
                ->where('currency', $request->currency)
                ->update([
                    $clearedCol => DB::raw("$clearedCol - " . $request->amount),
                    'balance'   => DB::raw("balance - " . $request->amount)
                ]);

            NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => 'completed',
                'charge' => 0,
                'net_amount' => $request->amount,
                'meta' => ['note' => 'User initiated withdrawal']
            ]);

            return response()->json(['success' => true]);
        });
    }

    
    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'currency' => 'required|in:NGN,USD'
        ]);

        $user = Auth::user();

        return DB::transaction(function () use ($request, $user) {
            
            $clearedCol = $request->currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

            
            \App\Models\Wallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => $request->currency],
                ['balance' => 0, 'status' => 'active', 'ngn_cleared' => 0, 'usd_cleared' => 0, 'locked' => 0]
            );

            DB::table('wallets')
                ->where('user_id', $user->id)
                ->where('currency', $request->currency)
                ->update([
                    $clearedCol => DB::raw("$clearedCol + " . $request->amount),
                    'balance'   => DB::raw("balance + " . $request->amount)
                ]);

            NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => 'completed',
                'charge' => 0,
                'net_amount' => $request->amount,
                'meta' => [
                    'reference' => 'DEP-' . strtoupper(bin2hex(random_bytes(4)))
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully deposited " . number_format($request->amount, 2)
            ]);
        });
    }

    public function convert(Request $request)
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
            ]);

            $ngnAmount = (float) $validated['amount'];

            return DB::transaction(function () use ($user, $ngnAmount) {
                $walletNgn = \App\Models\Wallet::where('user_id', $user->id)->where('currency', 'NGN')->first();

                if (!$walletNgn || ($walletNgn->ngn_cleared ?? 0) < $ngnAmount) {
                    return response()->json(['error' => 'Insufficient cleared NGN balance'], 400);
                }

                $rate = FxRate::where('from_currency', 'NGN')
                    ->where('to_currency', 'USD')
                    ->latest()
                    ->first();

                if (! $rate) {
                    return response()->json(['error' => 'FX rate not configured'], 400);
                }

                $usdAmount = $ngnAmount / $rate->effective_rate;

                DB::table('wallets')
                    ->where('user_id', $user->id)
                    ->where('currency', 'NGN')
                    ->update([
                        'ngn_cleared' => DB::raw("ngn_cleared - " . $ngnAmount),
                        'balance'     => DB::raw("balance - " . $ngnAmount)
                    ]);

                \App\Models\Wallet::firstOrCreate(
                    ['user_id' => $user->id, 'currency' => 'USD'],
                    ['balance' => 0, 'status' => 'active', 'usd_cleared' => 0, 'usd_uncleared' => 0, 'locked' => 0]
                );

                DB::table('wallets')
                    ->where('user_id', $user->id)
                    ->where('currency', 'USD')
                    ->update([
                        'usd_uncleared' => DB::raw("usd_uncleared + " . $usdAmount),
                        'balance'       => DB::raw("balance + " . $usdAmount)
                    ]);

                $txReference = 'FX-' . \Illuminate\Support\Str::uuid();

                // Record conversion ledger entry
                Ledger::create([
                    'user_id' => $user->id,
                    'currency' => 'USD',
                    'amount' => $usdAmount,
                    'type' => 'FX_CONVERSION',
                    'status' => 'pending',
                    'reference' => $txReference,
                    'meta' => [
                        'ngn_amount' => $ngnAmount,
                        'locked_rate' => $rate->effective_rate,
                        'base_rate' => $rate->base_rate,
                    ],
                ]);

                // Track platform profit
                $usdAtBaseRate = $ngnAmount / $rate->base_rate;
                $platformProfitUsd = $usdAtBaseRate - $usdAmount;

                if ($platformProfitUsd > 0) {
                    Ledger::create([
                        'user_id' => null,
                        'currency' => 'USD',
                        'amount' => $platformProfitUsd,
                        'type' => 'FX_MARKUP_PROFIT',
                        'is_platform' => true,
                        'meta' => [
                            'converted_user_id' => $user->id,
                            'ngn_amount' => $ngnAmount,
                            'rate_used' => $rate->effective_rate,
                        ],
                    ]);
                }

                NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'fx_conversion',
                    'amount' => $ngnAmount,
                    'currency' => 'NGN',
                    'status' => 'pending',
                    'meta' => [
                        'reference' => $txReference,
                        'to_currency' => 'USD',
                        'received_amount' => $usdAmount,
                        'exchange_rate' => $rate->effective_rate,
                        'note' => "Converted ₦" . number_format($ngnAmount, 2) . " to $" . number_format($usdAmount, 2)
                    ]
                ]);

                ActivityLog::log($user->id, 'fx_conversion_initiated', [
                    'ngn_amount' => $ngnAmount,
                    'usd_amount' => $usdAmount,
                    'rate' => $rate->effective_rate,
                ]);

                return response()->json([
                    'success' => true,
                    'amount_usd' => $usdAmount,
                    'ngn_debited' => $ngnAmount,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Conversion failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getTotalBalance($user, $currency)
    {
        $w = $user->wallet()->where('currency', $currency)->first();

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

        $transactions = NewTransaction::where('user_id', $user->id)
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
        $rate = 0.00065;

        if ($from === 'NGN') {
            $preview = $amount * $rate;
            $label = 'USD';
        } else {
            $preview = $amount / $rate;
            $label = 'NGN';
        }

        return response()->json([
            'converted' => round($preview, 2),
            'currency' => $label,
            'rate' => $rate,
        ]);
    }
}