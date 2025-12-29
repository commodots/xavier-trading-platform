<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Wallet;
use App\Models\Transaction; // Ensure this is the correct model name

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
                'balance_ngn' => (float)($ngnWallet?->balance ?? 0),
                'balance_usd' => (float)($usdWallet?->balance ?? 0),
            ]
        ]);
    }

    public function convert(Request $request)
    {
        $request->validate([
            'from' => 'required|in:NGN,USD',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = Auth::user();
        $amount = $request->amount;
        $from = $request->from;
        $target = ($from === 'NGN') ? 'USD' : 'NGN';

        // 1. Get both wallet rows
        $fromWallet = $user->wallet()->where('currency', $from)->first();
        $toWallet = $user->wallet()->where('currency', $target)->first();

        if (!$fromWallet || $fromWallet->balance < $amount) {
            return response()->json(['success' => false, 'message' => "Insufficient $from balance"], 400);
        }

        // 2. Fetch FX rate (Note: exchangerate.host often requires an API key now, check their docs) 
        $fx = Http::get("https://api.exchangerate.host/latest", [
            'base' => 'NGN',
            'symbols' => 'USD'
        ])->json();

        $rate = $fx['rates']['USD'] ?? 0.00065; // Default fallback rate if API fails

        // 3. Perform Calculation
        if ($from === 'NGN') {
            $convertedAmount = $amount * $rate;
        } else {
            $convertedAmount = $amount / $rate;
        }

        // 4. Update Database (Atomic Transactions would be better here)
        $fromWallet->decrement('balance', $amount);

        // Ensure the target wallet exists, if not create it
        if (!$toWallet) {
            $toWallet = $user->wallet()->create(['currency' => $target, 'balance' => 0]);
        }
        $toWallet->increment('balance', $convertedAmount);

        return response()->json([
            'success' => true,
            'message' => 'Conversion completed.',
            'data' => [
                'balance_ngn' => $user->wallet()->where('currency', 'NGN')->first()?->balance ?? 0,
                'balance_usd' => $user->wallet()->where('currency', 'USD')->first()?->balance ?? 0,
            ]
        ]);
    }

    public function recentTransactions(Request $request)
    {
        $user = $request->user();

        // We use the Transaction model directly to avoid relationship errors
        $transactions = Transaction::where('user_id', $user->id)
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
                'ref' => $t->reference ?? $t->id
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => $formattedTransactions
        ]);
    }
    public function preview(Request $request)
    {
        $amount = $request->query('amount', 0);
        $from = $request->query('from', 'NGN');

     
        $rate = 0.00065;

        if ($from === 'NGN') {
            $preview = $amount * $rate;
            $label = "USD";
        } else {
            $preview = $amount / $rate;
            $label = "NGN";
        }

        return response()->json([
            'converted' => round($preview, 2),
            'currency' => $label,
            'rate' => $rate
        ]);
    }
}
