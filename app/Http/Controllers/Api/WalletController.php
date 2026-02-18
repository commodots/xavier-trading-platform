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
                'balance_ngn' => (float) (($ngnWallet?->ngn_cleared ?? 0) + ($ngnWallet?->ngn_uncleared ?? 0)),
                'balance_usd' => (float) (($usdWallet?->usd_cleared ?? 0) + ($usdWallet?->usd_uncleared ?? 0)),

                'cleared_balance_ngn' => (float) ($ngnWallet?->ngn_cleared ?? 0),
                'uncleared_balance_ngn' => (float) ($ngnWallet?->ngn_uncleared ?? 0),
                'locked_balance_ngn' => (float) ($ngnWallet?->locked ?? 0), 

                'cleared_balance_usd' => (float) ($usdWallet?->usd_cleared ?? 0),
                'uncleared_balance_usd' => (float) ($usdWallet?->usd_uncleared ?? 0),
                'locked_balance_usd' => (float) ($usdWallet?->locked ?? 0),
            ],
        ]);
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
                $walletNgn = $user->fxWallet('NGN');
                $walletUsd = $user->fxWallet('USD');

                if (($walletNgn->ngn_cleared ?? 0) < $ngnAmount) {
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

                // Move funds atomically
                $walletNgn->debit($ngnAmount, 'cleared');
                $walletUsd->credit($usdAmount, 'uncleared');

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
                    'reference' => $txReference,
                    'meta' => [
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
