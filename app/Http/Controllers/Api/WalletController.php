<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WalletController extends Controller
{
    // Fetch balances
    public function balances()
    {
        $user = Auth::user();
        $wallet = $user->wallet;

        if (!$wallet) {
            $wallet = $user->wallet()->create([
                'balance_ngn' => 0,
                'balance_usd' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $wallet
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
}
