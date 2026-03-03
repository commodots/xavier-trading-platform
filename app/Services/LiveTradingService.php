<?php

namespace App\Services;

use App\Models\{Order, Wallet, Portfolio, Trade, User,};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Demo\DemoWallet;
use App\Models\Demo\DemoOrder;


class LiveTradingService
{
    public function executeTrade($user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $marketMap = match ($data['market']) {
                "NGX" => ['currency' => 'NGN', 'category' => 'local'],
                "GLOBAL" => ['currency' => 'USD', 'category' => 'foreign'],
                "CRYPTO" => ['currency' => 'USD', 'category' => 'crypto'],
                "USD" => ['currency' => 'USD', 'category' => 'foreign'], // Fallback
                default => throw new \InvalidArgumentException("Unsupported market type: {$data['market']}")
            };

            $currency = $marketMap['currency'];
            $category = $marketMap['category'];

            $units = floor($data['amount'] / $data['market_price']);
            if ($units <= 0) {
                throw new \InvalidArgumentException("Invalid trade amount: must be greater than market price");
            }
            $actualCost = $units * $data['market_price'];

            // Lock the wallet to prevent double-spending
            $wallet = Wallet::where('user_id', $user->id)->where('currency', $currency)->lockForUpdate()->firstOrFail();

            $holding = Portfolio::firstOrCreate(
                ['user_id' => $user->id, 'symbol' => $data['symbol']],
                [
                    'name' => $data['company'],
                    'currency' => $currency,
                    'category' => $category,
                    'quantity' => 0,
                    'avg_price' => 0
                ]
            );

            $clearedCol = $currency === 'NGN' ? 'ngn_cleared' : 'usd_cleared';

            if ($data['side'] === 'buy') {
                if ($wallet->$clearedCol < $actualCost) {
                    throw new \Exception("Insufficient cleared {$currency} balance");
                }

                $wallet->decrement($clearedCol, $actualCost);
                $wallet->increment('locked', $actualCost);

                // Math: (Current Value + New Value) / Total Quantity
                $totalQty = $holding->quantity + $units;
                $newAvgPrice = (($holding->quantity * $holding->avg_price) + $actualCost) / $totalQty;

                $holding->update([
                    'quantity' => $totalQty,
                    'uncleared_quantity' => $holding->uncleared_quantity + $units,
                    'avg_price' => $newAvgPrice
                ]);
            } else {
                // Sell logic: check if user has enough "Cleared" stock to sell
                if ($holding->cleared_quantity < $units) {
                    throw new \Exception("Insufficient cleared holdings to sell");
                }

                $holding->decrement('cleared_quantity', $units);
                $holding->decrement('quantity', $units); // Total holdings drop immediately

                // Money goes to uncleared balance (T+2)
                $unclearedCol = $currency === 'NGN' ? 'ngn_uncleared' : 'usd_uncleared';
                $wallet->increment($unclearedCol, $actualCost);
            }

            $order = Order::create([...$data, 'user_id' => $user->id, 'status' => 'filled', 'units' => $units]);
            $settlementDays = ($user->trading_mode === 'demo') ? 0 : 2;

            Trade::create([
                'order_id' => $order->id,
                'price' => $data['market_price'],
                'quantity' => $units,
                'settlement_date' => Carbon::now()->addWeekdays($settlementDays)->toDateString(),
            ]);

            return $order;
        });
    }

    public function getPortfolio($userId)
    {
        $FX_RATE = 1500;

        $user = User::find($userId); // Convert the ID to a User Object

        // Determine which models to use based on the user's current mode
        $isDemo = $user->trading_mode === 'demo';
        $portfolioModel = $isDemo ? DemoOrder::class : Portfolio::class;
        $walletModel = $isDemo ? DemoWallet::class : Wallet::class;

        $rawHoldings = $portfolioModel::where('user_id', $userId)->where('quantity', '>', 0)->get();

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

        $ngnWallet = $walletModel::where('user_id', $userId)->where('currency', 'NGN')->first();
        $usdWallet = $walletModel::where('user_id', $userId)->where('currency', 'USD')->first();

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

        return [
            'success' => true,
            'trading_mode' => $user->trading_mode,
            'wallet_balance' => $walletValueNgn,
            'ngx_value' => $ngxValue,
            'fixed_income_value' => $fixedIncomeValue,
            'global_stocks_value_usd' => $globalValueUsd,
            'global_stocks_value_ngn' => $globalValueNgn,
            'crypto_value_usd' => $cryptoValueUsd,
            'crypto_value_ngn' => $cryptoValueNgn,
            'holdings' => $groupedHoldings,
            'total_equity' => ($walletValueNgn + $ngxValue + $globalValueNgn + $cryptoValueNgn + $fixedIncomeValue),
            'portfolio_distribution' => [
        ['label' => 'Wallet', 'value' => $walletValueNgn],
        ['label' => 'NGX', 'value' => $ngxValue],
        ['label' => 'Global', 'value' => $globalValueNgn],
        ['label' => 'Crypto', 'value' => $cryptoValueNgn],
        ['label' => 'Fixed Income', 'value' => $fixedIncomeValue],
    ]
        ];
    }
}
