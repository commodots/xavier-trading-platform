<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use App\Models\Portfolio;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $FX_RATE = 1500; //fetch from an api later

        $rawHoldings = Portfolio::where('user_id', $user->id)->get();

        $groupedHoldings = $rawHoldings->groupBy('symbol')->map(function ($items) use ($FX_RATE) {
            $first = $items->first();
            $totalQuantity = $items->sum('quantity');

            // Formula: Sum(qty * price) / Total Qty

            $totalCost = $items->sum(fn($i) => $i->quantity * $i->avg_price);
            $weightedAvgPrice = $totalQuantity > 0 ? ($totalCost / $totalQuantity) : 0;

            $currentValueRaw = $totalQuantity * $first->market_price;

            // Determine if we need to convert this specific row to NGN
            $isUsd = ($first->currency === 'USD' || $first->category === 'foreign' || $first->category === 'crypto');
            $multiplier = $isUsd ? $FX_RATE : 1;

            return [
                'symbol' => $first->symbol,
                'name' => $first->name,
                'category' => $first->category,
                'currency' => $first->currency,
                'quantity' => $totalQuantity,
                'avg_price' => $weightedAvgPrice,
                'market_price' => $first->market_price,
                'total_value' => $currentValueRaw,
                'avg_price_ngn' => $weightedAvgPrice * $multiplier,
                'total_value_ngn' => $currentValueRaw * $multiplier,
            ];
        })->values();

        $ngnWallet = $user->wallet()->where('currency', 'NGN')->first();
        $usdWallet = $user->wallet()->where('currency', 'USD')->first();

        $ngnBalance = $ngnWallet->balance ?? 0;
        $usdBalance = $usdWallet->balance ?? 0;

        // Calculate Category Values (Converting USD assets to NGN for the Chart)
        $ngxValue = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'local')->sum('total_value_ngn');
        $cryptoValue = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'crypto')->sum('total_value_ngn');

        $globalValueUsd = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'foreign')->sum('total_value_ngn');
        $globalValueNgn = $globalValueUsd * $FX_RATE;
        // Calculate Total Wallet Value in NGN
        $walletValueNgn = (float) ($ngnBalance + ($usdBalance * $FX_RATE));


        return response()->json([
            'success' => true,
            'wallet_balance' => $walletValueNgn,
            'ngx_value' => $ngxValue,
            'global_stocks_value_ngn' => $globalValueNgn,
            'crypto_value' => $cryptoValue,
            'holdings' => $groupedHoldings,
            'total_equity' => ($walletValueNgn + $ngxValue + $globalValueNgn + $cryptoValue)
        ]);
    }
}
