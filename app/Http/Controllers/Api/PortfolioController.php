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
            $clearedQuantity = $items->sum('cleared_quantity');
            $unclearedQuantity = $items->sum('uncleared_quantity');

            // Formula: Sum(qty * price) / Total Qty
            $totalCost = $items->sum(fn($i) => $i->quantity * $i->avg_price);
            $weightedAvgPrice = $totalQuantity > 0 ? ($totalCost / $totalQuantity) : 0;

            $currentValueRaw = $totalQuantity * $first->market_price;

            // Determine if we need to convert this specific row to NGN
            $isUsd = ($first->currency === 'USD' || $first->category === 'foreign' || $first->category === 'crypto');
            $multiplier = $isUsd ? $FX_RATE : 1;

            $isCrypto = strtolower($first->category) === 'crypto';
            $calculatedTotal = $clearedQuantity + $unclearedQuantity;
            $displayQuantity = $isCrypto ? $calculatedTotal : floor($calculatedTotal);
            $displayCleared = $isCrypto ? $clearedQuantity : floor($clearedQuantity);
            $displayUncleared = $isCrypto ? $unclearedQuantity : floor($unclearedQuantity);

            // Skip holdings with zero total quantity
            if ($totalQuantity <= 0) {
                return null;
            }

            return [
                'symbol' => $first->symbol,
                'name' => $first->name,
                'category' => $first->category,
                'currency' => $first->currency,
                'quantity' => $isCrypto ? $totalQuantity : floor($totalQuantity),
                'cleared_quantity' => $displayCleared,
                'uncleared_quantity' => $displayUncleared,
                'avg_price' => $weightedAvgPrice,
                'market_price' => $first->market_price,
                'total_value' => $currentValueRaw,
                'avg_price_ngn' => (float) ($weightedAvgPrice * $multiplier),
                'total_value_ngn' => (float) ($currentValueRaw * $multiplier),
            ];
        })->filter()->values();

        $ngnWallet = $user->wallet()->where('currency', 'NGN')->first();
        $usdWallet = $user->wallet()->where('currency', 'USD')->first();

        $ngnBalance = $ngnWallet->balance ?? 0;
        $usdBalance = $usdWallet->balance ?? 0;

        // Calculate Category Values (Converting USD assets to NGN for the Chart)

        $globalValueUsd = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'foreign')->sum('total_value');

        $globalValueNgn = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'foreign')->sum('total_value_ngn');

        $ngxValue = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'local')->sum('total_value_ngn');

        $fixedIncomeValue = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'fixed_income')->sum('total_value_ngn');

        $cryptoValueNgn = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'crypto')->sum('total_value_ngn');

        $cryptoValueUsd = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'crypto')->sum('total_value');

        // Calculate Total Wallet Value in NGN
        $walletValueNgn = (float) ($ngnBalance + ($usdBalance * $FX_RATE));


        return response()->json([
            'success' => true,
            'wallet_balance' => $walletValueNgn,
            'ngx_value' => $ngxValue,
            'fixed_income_value' => $fixedIncomeValue,
            'global_stocks_value_usd' => $globalValueUsd,
            'global_stocks_value_ngn' => $globalValueNgn,
            'crypto_value_ngn' => $cryptoValueNgn,
            'crypto_value_usd' => $cryptoValueUsd,
            'holdings' => $groupedHoldings,
            'total_equity' => ($walletValueNgn + $ngxValue + $globalValueNgn + $cryptoValueNgn + $fixedIncomeValue)
        ]);
    }
 public function performance(Request $request)
{
    $user = $request->user();
    $category = $request->query('category', 'local');
    $range = $request->query('range', '1W');

    
    $holdings = Portfolio::where('user_id', $user->id)
        ->where('category', $category)
        ->get();

    $days = match($range) {
        '1D' => 1,
        '1W' => 7,
        '1M' => 30,
        default => 7
    };

    
    $multiSeries = [];
    $totalCurrentValue = 0;

    foreach ($holdings as $holding) {
        $dataPoints = [];
        $now = now();

        for ($i = $days; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            
            $historicalPrice = $holding->market_price * (1 - ($i * 0.005)); 
            
            $dataPoints[] = [
                'x' => $date->timestamp * 1000,
                'y' => round($holding->quantity * $historicalPrice, 2)
            ];
        }

        $multiSeries[] = [
            'name' => $holding->symbol,
            'data' => $dataPoints
        ];

        $totalCurrentValue += ($holding->quantity * $holding->market_price);
    }

    return response()->json([
        'success' => true,
        'series' => $multiSeries, 
        'total' => $totalCurrentValue,
        'change' => 1.25 
    ]);
}
}
