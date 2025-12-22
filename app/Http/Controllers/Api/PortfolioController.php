<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Wallet;

class PortfolioController extends Controller
{
    public function summary(Request $request)
    {
        // app/Http/Controllers/Api/TransactionController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // C1. index() user transaction history [cite: 63]
    public function index()
    {
        return response()->json(auth()->user()->transactions()->latest()->get());
    }

    // C1. deposit() [cite: 61]
    public function deposit(Request $request)
    {
        // No wallet logic yet - just record rows [cite: 64]
        $transaction = Transaction::create([
            'user_id' => auth()->id(), [cite: 67]
            'type' => 'deposit', [cite: 68]
            'amount' => $request->amount, [cite: 69]
            'currency' => $request->currency ?? 'NGN', [cite: 70]
            'status' => 'completed' [cite: 71]
        ]);
        return response()->json($transaction);
    }

    // C1. withdraw() [cite: 62]
    public function withdraw(Request $request)
    {
        $transaction = Transaction::create([
            'user_id' => auth()->id(),
            'type' => 'withdrawal',
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'NGN',
            'status' => 'pending'
        ]);
        return response()->json($transaction);
    }
}

        // --- 1. Define Placeholder/Hardcoded Values ---
        // In a real app, these would come from the database (e.g., Holdings table) or a real-time price API.
        $FX_RATE = 1500; // Placeholder: 1 USD = 1500 NGN
        $NGX_VALUE = 250000;
        $GLOBAL_STOCKS_VALUE_USD = 150;
        $CRYPTO_VALUE_NGN = 50000;

        // --- 2. Fetch Wallet Balances ---
        // Fetch NGN and USD wallet records
        $ngnWallet = $user->wallet()->where('currency', 'NGN')->first();
        $usdWallet = $user->wallet()->where('currency', 'USD')->first();

        // Safely get balances, defaulting to 0 if the wallet record doesn't exist
        $ngnBalance = $ngnWallet->balance ?? 0;
        $usdBalance = $usdWallet->balance ?? 0;

        // --- 3. Calculate Total Equity (in NGN) ---
        $walletValueNgn = $ngnBalance + ($usdBalance * $FX_RATE);

        $totalEquity = $walletValueNgn + $NGX_VALUE + ($GLOBAL_STOCKS_VALUE_USD * $FX_RATE) + $CRYPTO_VALUE_NGN;

        // --- 4. Prepare Holdings Data (Simulated for now) ---
        $holdingsData = [
            // Wallet NGN/USD are often listed separately in a portfolio view
            [
                'symbol' => 'NGN Wallet',
                'qty' => 1, // Represents 1 unit of NGN wallet
                'avg_cost' => $ngnBalance,
                'market_price' => $ngnBalance,
            ],
            [
                'symbol' => 'USD Wallet',
                'qty' => $usdBalance,
                'avg_cost' => $usdBalance, // Assume cost is the current USD balance
                'market_price' => $usdBalance,
            ],
            // Placeholder for actual assets
            [
                'symbol' => 'NGX:GTCO',
                'qty' => 500,
                'avg_cost' => 300,
                'market_price' => 500,
            ],
            [
                'symbol' => 'BTC',
                'qty' => 0.005,
                'avg_cost' => 8000000,
                'market_price' => 10000000,
            ],
        ];
        $transactions = \App\Models\Transaction::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        // --- 5. Return JSON Response matching Vue's Expectation ---
        return response()->json([
            'success' => true,
            'total_equity' => (int) $totalEquity, // Must be the grand total
            'holdings' => $holdingsData, // The detailed table list
            'transactions' => $transactions,
            // Data for the Pie Chart Series (must be in NGN terms for a total NGN equity chart)
            'wallet_balance' => (int) $walletValueNgn,
            'ngx_value' => (int) $NGX_VALUE,
            'global_stocks_value_usd' => (int) ($GLOBAL_STOCKS_VALUE_USD * $FX_RATE), // Converted to NGN
            'crypto_value' => (int) $CRYPTO_VALUE_NGN,
        ]);
    }
}
