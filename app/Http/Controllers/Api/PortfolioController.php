<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Portfolio;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $FX_RATE = 1500; // fetch from an api later

        $rawHoldings = Portfolio::where('user_id', $user->id)->get();

        $groupedHoldings = $rawHoldings->groupBy('symbol')->map(function ($items) use ($FX_RATE) {
            $first = $items->first();
            
            // Calculate total quantities based on cleared + uncleared
            $clearedQuantity = (float) $items->sum('cleared_quantity');
            $unclearedQuantity = (float) $items->sum('uncleared_quantity');
            $totalQuantity = $clearedQuantity + $unclearedQuantity;

            // Skip holdings with zero total quantity (e.g., fully sold off)
            if ($totalQuantity <= 0) {
                return null;
            }

            // Formula: Sum(qty * avg_price) / Total Qty
            $totalCost = $items->sum(fn($i) => ($i->cleared_quantity + $i->uncleared_quantity) * $i->avg_price);
            $weightedAvgPrice = $totalCost / $totalQuantity;

            $currentValueRaw = $totalQuantity * $first->market_price;

            // Determine if we need to convert this specific row to NGN
            $isUsd = ($first->currency === 'USD' || $first->category === 'foreign' || $first->category === 'crypto');
            $multiplier = $isUsd ? $FX_RATE : 1;

            $isCrypto = strtolower($first->category) === 'crypto';
            
            $displayQuantity = $isCrypto ? $totalQuantity : floor($totalQuantity);
            $displayCleared = $isCrypto ? $clearedQuantity : floor($clearedQuantity);
            $displayUncleared = $isCrypto ? $unclearedQuantity : floor($unclearedQuantity);

            return [
                'symbol' => $first->symbol,
                'name' => $first->name ?? $first->symbol,
                'category' => $first->category,
                'currency' => $first->currency,
                'quantity' => $displayQuantity,
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

        $ngnBalance = $ngnWallet ? (float) $ngnWallet->ngn_cleared : 0;
        $usdBalance = $usdWallet ? (float) $usdWallet->usd_cleared : 0;

        // Calculate Category Values (Converting USD assets to NGN for the Chart)

        $globalValueUsd = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'foreign')->sum('total_value');
        $globalValueNgn = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'foreign')->sum('total_value_ngn');
        
        $ngxValue = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'local')->sum('total_value_ngn');
        $fixedIncomeValue = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'fixed_income')->sum('total_value_ngn');
        
        $cryptoValueUsd = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'crypto')->sum('total_value');
        $cryptoValueNgn = (float) $groupedHoldings->filter(fn($h) => $h['category'] === 'crypto')->sum('total_value_ngn');

        // Calculate Total Wallet Value in NGN
        $walletValueNgn = (float) ($ngnBalance + ($usdBalance * $FX_RATE));

        return response()->json([
            'success' => true,
            'wallet_balance' => $walletValueNgn,
            'ngx_value' => $ngxValue,
            'fixed_income_value' => $fixedIncomeValue,
            'global_stocks_value_usd' => $globalValueUsd,
            'global_stocks_value_ngn' => $globalValueNgn,
            'crypto_value_usd' => $cryptoValueUsd,
            'crypto_value_ngn' => $cryptoValueNgn,
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
                
                
                $totalQty = $holding->cleared_quantity + $holding->uncleared_quantity;
                
                $dataPoints[] = [
                    'x' => $date->timestamp * 1000,
                    'y' => round($totalQty * $historicalPrice, 2)
                ];
            }

            $multiSeries[] = [
                'name' => $holding->symbol,
                'data' => $dataPoints
            ];

            $totalQty = $holding->cleared_quantity + $holding->uncleared_quantity;
            $totalCurrentValue += ($totalQty * $holding->market_price);
        }

        return response()->json([
            'success' => true,
            'series' => $multiSeries, 
            'total' => $totalCurrentValue,
            'change' => 1.25 
        ]);
    }
}