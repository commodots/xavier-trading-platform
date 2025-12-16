<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Wallet;
use App\Models\Transaction;


class WalletController extends Controller
{
    // Fetch balances
    public function balances()
    {
        $user = Auth::user();
        
        // --- 1. Fetch Wallets from separate rows ---
        // Get the specific NGN and USD wallet objects
        $ngnWallet = $user->wallets()->where('currency', 'NGN')->first();
        $usdWallet = $user->wallets()->where('currency', 'USD')->first();

        // --- 2. Consolidate and Default (NULL-SAFE) ---
        // Use the null-safe operator (?->) to prevent 500 errors if a wallet is missing.
        $consolidatedWallet = [
            'balance_ngn' => $ngnWallet?->balance ?? 0,
            'balance_usd' => $usdWallet?->balance ?? 0,
            'id' => $user->id, // Optional
        ];

        // --- 3. Return the consolidated object ---
        return response()->json([
            'success' => true,
            'data' => $consolidatedWallet
        ]);
    }

    // Convert currency NGN ↔ USD
    public function convert(Request $request)
    {
        $request->validate([
            'from' => 'required|in:NGN,USD',
            'amount' => 'required|numeric|min:1',
        ]);

        $user = Auth::user();
        $wallet = $user->wallet;

        // Fetch FX rate
        $fx = Http::get("https://api.exchangerate.host/latest", [
            'base' => 'NGN',
            'symbols' => 'USD'
        ])->json();

        $rate = $fx['rates']['USD'] ?? null;

        if (!$rate) {
            return response()->json(['success' => false, 'message' => 'FX service unavailable'], 503);
        }

        $amount = $request->amount;
        $from = $request->from;

        if ($from === 'NGN') {
            if ($wallet->balance_ngn < $amount) {
                return response()->json(['success' => false, 'message' => 'Insufficient NGN balance']);
            }

            $usd = $amount * $rate;

            $wallet->balance_ngn -= $amount;
            $wallet->balance_usd += $usd;
        } else {
            if ($wallet->balance_usd < $amount) {
                return response()->json(['success' => false, 'message' => 'Insufficient USD balance']);
            }

            $ngn = $amount / $rate;

            $wallet->balance_usd -= $amount;
            $wallet->balance_ngn += $ngn;
        }

        $wallet->save();

        return response()->json([
            'success' => true,
            'message' => 'Conversion completed.',
            'data' => $wallet
        ]);
    }
    public function recentTransactions(Request $request)
    {
        // Fetch the last 10 transactions for the authenticated user, newest first
        $transactions = $request->user()
            ->transactions() // Assumes you have a 'transactions' relationship on your User model
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        // Map the transactions to the format expected by the Vue component
        $formattedTransactions = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                // Format date to match your Vue template (e.g., "2025-11-01")
                'date' => $transaction->created_at->format('Y-m-d'), 
                'type' => ucfirst($transaction->type), // E.g., "Deposit"
                'currency' => $transaction->asset, // Using 'asset' column for currency/asset symbol
                'amount' => (float) $transaction->amount, // Cast to float for precision
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => $formattedTransactions
        ]);
    }

}
