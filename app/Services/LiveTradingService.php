<?php

namespace App\Services;

use App\Models\{Order, Wallet, Portfolio, Trade, User};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Demo\DemoWallet;
use App\Models\Demo\DemoOrder;


class LiveTradingService
{
   
    public function executeTrade($user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $marketMap = match (strtoupper($data['market'])) {
                "NGX"          => ['currency' => 'NGN', 'category' => 'local'],
                "GLOBAL", "USD" => ['currency' => 'USD', 'category' => 'foreign'],
                "CRYPTO"       => ['currency' => 'USD', 'category' => 'crypto'],
                "FIXED_INCOME" => ['currency' => 'NGN', 'category' => 'fixed_income'],
                default        => throw new \InvalidArgumentException("Unsupported market: {$data['market']}")
            };

            $currency = $marketMap['currency'];
            $category = $marketMap['category'];

            // Quantity & Cost Calculation
            // We use 1500 as a placeholder; in production, fetch this from a Cache/Rate service
            $FX_RATE = 1500; 
            $priceInNaira = ($currency === 'USD') ? ($data['market_price'] * $FX_RATE) : $data['market_price'];
            
            
            $units = ($category === 'crypto') 
                ? ($data['amount'] / $priceInNaira) 
                : floor($data['amount'] / $priceInNaira);

            if ($units <= 0) {
                throw new \InvalidArgumentException("Trade amount is too low to purchase 1 unit.");
            }

            $actualCost = $units * $data['market_price'];

            
            $wallet = Wallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->lockForUpdate() 
                ->firstOrFail();

            $holding = Portfolio::lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id, 'symbol' => $data['symbol']],
                [
                    'name' => $data['company'],
                    'currency' => $currency,
                    'category' => $category,
                    'quantity' => 0,
                    'avg_price' => 0,
                    'market_price' => $data['market_price'],
                    'cleared_quantity' => 0,
                    'uncleared_quantity' => 0
                ]
            );

            $clearedCol = ($currency === 'NGN') ? 'ngn_cleared' : 'usd_cleared';

            if ($data['side'] === 'buy') {
                if ($wallet->$clearedCol < $actualCost) {
                    throw new \Exception("Insufficient cleared {$currency} balance.");
                }

               
                $wallet->decrement($clearedCol, $actualCost);
                $wallet->increment('locked', $actualCost);

                
                $totalQty = $holding->quantity + $units;
                $newAvgPrice = (($holding->quantity * $holding->avg_price) + $actualCost) / $totalQty;

                $holding->update([
                    'quantity' => $totalQty,
                    'uncleared_quantity' => $holding->uncleared_quantity + $units,
                    'avg_price' => $newAvgPrice
                ]);
            } else {
                // SELL LOGIC
                if ($holding->cleared_quantity < $units) {
                    throw new \Exception("Insufficient cleared holdings to sell. Available: {$holding->cleared_quantity}");
                }

                $holding->decrement('cleared_quantity', $units);
                $holding->decrement('quantity', $units);

                // Proceeds go to 'uncleared' wallet balance for T+2
                $unclearedCol = ($currency === 'NGN') ? 'ngn_uncleared' : 'usd_uncleared';
                $wallet->increment($unclearedCol, $actualCost);
            }

            $order = Order::create([
                ...$data,
                'user_id' => $user->id,
                'status' => 'filled', 
                'units' => $units,
                'quantity' => $units
            ]);

            Trade::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'price' => $data['market_price'],
                'quantity' => $units,
                'side' => $data['side'],
                'currency' => $currency,
                'settlement_status' => 'pending',
                'settlement_date' => Carbon::now()->addWeekdays(2)->toDateString(),
            ]);

            return $order;
        });
    }

    
    public function getPortfolio($userId)
    {
        $FX_RATE = 1500;
        $rawHoldings = Portfolio::where('user_id', $userId)->where('quantity', '>', 0)->get();

        $groupedHoldings = $rawHoldings->map(function ($holding) use ($FX_RATE) {
            $isUsd = in_array($holding->currency, ['USD']) || in_array($holding->category, ['foreign', 'crypto']);
            $multiplier = $isUsd ? $FX_RATE : 1;
            
            $currentValue = $holding->quantity * $holding->market_price;

            return [
                'symbol' => $holding->symbol,
                'name' => $holding->name,
                'category' => $holding->category,
                'currency' => $holding->currency,
                'quantity' => (float) $holding->quantity,
                'cleared_quantity' => (float) $holding->cleared_quantity,
                'uncleared_quantity' => (float) $holding->uncleared_quantity,
                'avg_price' => (float) $holding->avg_price,
                'market_price' => (float) $holding->market_price,
                'total_value' => $currentValue,
                'total_value_ngn' => $currentValue * $multiplier,
                'gain_loss' => ($holding->market_price - $holding->avg_price) * $holding->quantity,
            ];
        });

        // Fetch Wallet Balances
        $wallets = Wallet::where('user_id', $userId)->get();
        $ngnWallet = $wallets->where('currency', 'NGN')->first();
        $usdWallet = $wallets->where('currency', 'USD')->first();

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