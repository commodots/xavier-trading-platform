<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiveTradingService
{
    public function executeTrade($user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $marketMap = match (strtoupper($data['market'])) {
                'NGX' => ['currency' => 'NGN', 'category' => 'local'],
                'GLOBAL', 'USD' => ['currency' => 'USD', 'category' => 'foreign'],
                'CRYPTO' => ['currency' => 'USD', 'category' => 'crypto'],
                'FIXED_INCOME' => ['currency' => 'NGN', 'category' => 'fixed_income'],
                default => throw new \InvalidArgumentException("Unsupported market: {$data['market']}")
            };

            $currency = $marketMap['currency'];
            $category = $marketMap['category'];

            // Quantity & Cost Calculation
            $FX_RATE = cache()->remember('usd_ngn_rate', 3600, function () {
                return \App\Models\FxRate::where('from_currency', 'USD')
                    ->where('to_currency', 'NGN')
                    ->latest()
                    ->value('effective_rate') ?? 1500;
            });
            $priceInNaira = ($currency === 'USD') ? ($data['market_price'] * $FX_RATE) : $data['market_price'];

            $units = ($category === 'crypto')
                ? ($data['amount'] / $priceInNaira)
                : floor($data['amount'] / $priceInNaira);

            if ($units <= 0) {
                throw new \InvalidArgumentException('Trade amount is too low to purchase 1 unit.');
            }

            $actualCost = $units * $data['market_price'];

            $wallet = Wallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->firstOrFail();

            // Derive holdings from trades for crypto bypass Portfolio table
            $holding = null;
            if ($category !== 'crypto') {
                $holding = Portfolio::where('user_id', $user->id)
                    ->where('symbol', $data['symbol'])
                    ->lockForUpdate()
                    ->firstOrCreate(
                        ['user_id' => $user->id, 'symbol' => $data['symbol']],
                        [
                            'name' => $data['company'],
                            'currency' => $currency,
                            'category' => $category,
                            'quantity' => 0,
                            'avg_price' => 0,
                            'market_price' => $data['market_price'],
                            'cleared_quantity' => 0,
                            'uncleared_quantity' => 0,
                        ]
                    );
            }

            $clearedCol = ($currency === 'NGN') ? 'ngn_cleared' : 'usd_cleared';

            if ($data['side'] === 'buy') {
                if ($actualCost > $wallet->$clearedCol) {
                    throw new \Exception("Insufficient cleared {$currency} balance.");
                }

                $wallet->decrement($clearedCol, $actualCost);
                $wallet->increment('locked', $actualCost);

                if ($holding) {
                    $totalQty = $holding->quantity + $units;
                    $newAvgPrice = (($holding->quantity * $holding->avg_price) + $actualCost) / $totalQty;

                    $holding->update([
                        'quantity' => $totalQty,
                        'uncleared_quantity' => $holding->uncleared_quantity + $units,
                        'avg_price' => $newAvgPrice,
                    ]);
                }
            } else {
                if ($holding && $holding->cleared_quantity < $units) {
                    throw new \Exception("Insufficient cleared holdings to sell. Available: {$holding->cleared_quantity}");
                }

                if ($holding) {
                    $holding->decrement('cleared_quantity', $units);
                    $holding->decrement('quantity', $units);
                }

                // Proceeds go to 'uncleared' wallet balance for T+2
                $unclearedCol = ($currency === 'NGN') ? 'ngn_uncleared' : 'usd_uncleared';
                $wallet->increment($unclearedCol, $actualCost);
                $wallet->increment('balance', $actualCost); // Fix: Sell proceeds must increase total balance
            }

            $order = Order::create([
                ...$data,
                'user_id' => $user->id,
                'status' => 'filled',
                'units' => $units,
                'quantity' => $units,
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
        $user = User::findOrFail($userId);

        $FX_RATE = cache()->get('usd_ngn_rate', function () {
            return \App\Models\FxRate::where('from_currency', 'USD')->where('to_currency', 'NGN')->latest()->value('effective_rate') ?? 1500; // Ensure consistency
        });

        $portfolioHoldings = Portfolio::where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->where('category', '!=', 'crypto')
            ->get();

        $groupedHoldings = $portfolioHoldings->map(function ($holding) use ($FX_RATE) {
            $isUsdAsset = in_array($holding->category, ['foreign', 'crypto'], true);
            $currentValue = $holding->quantity * $holding->market_price;

            // Calculate NGN equivalent for total equity tracking
            $totalValueNgn = $isUsdAsset ? ($currentValue * $FX_RATE) : $currentValue;

            // Calculate Average Price in NGN for P/L consistency in the table
            $avgPriceNgn = $isUsdAsset ? ($holding->avg_price * $FX_RATE) : $holding->avg_price;

            return [
                'symbol' => $holding->symbol,
                'name' => $holding->name,
                'category' => $holding->category,
                'currency' => $holding->currency,
                'quantity' => (float) $holding->quantity,
                'cleared_quantity' => (float) $holding->cleared_quantity,
                'uncleared_quantity' => (float) $holding->uncleared_quantity,
                'avg_price' => (float) $holding->avg_price,
                'avg_price_ngn' => (float) $avgPriceNgn,
                'market_price' => (float) $holding->market_price,
                'current_price' => (float) $holding->market_price,
                'total_cost' => (float) ($holding->avg_price * $holding->quantity),
                'total_value' => $currentValue,
                'total_value_ngn' => $totalValueNgn,
                'current_value' => $currentValue,
                'profit_loss' => ($currentValue - ($holding->avg_price * $holding->quantity)),
                'gain_loss' => ($holding->market_price - $holding->avg_price) * $holding->quantity,
            ];
        });

        $tradeHoldings = app(PortfolioService::class)->getUserPortfolio($userId);
        $tradeHoldings = app(PortfolioService::class)->attachMarketPrices($tradeHoldings);
        $holdings = $groupedHoldings->concat($tradeHoldings)->values();

        $wallets = Wallet::where('user_id', $userId)->get();
        $ngnBalance = (float) ($wallets->where('currency', 'NGN')->first()->ngn_cleared ?? 0);
        $usdBalance = (float) ($wallets->where('currency', 'USD')->first()->usd_cleared ?? 0);

        $globalValueUsd = (float) $holdings->filter(fn ($h) => $h['category'] === 'foreign')->sum('total_value');
        $cryptoValueUsd = (float) $holdings->filter(fn ($h) => $h['category'] === 'crypto')->sum('total_value');
        $ngxValueNgn = (float) $holdings->filter(fn ($h) => $h['category'] === 'local')->sum('total_value');
        $fixedIncomeNgn = (float) $holdings->filter(fn ($h) => $h['category'] === 'fixed_income')->sum('total_value');

        $walletValueNgn = $ngnBalance + ($usdBalance * $FX_RATE);
        $totalEquityNgn = $walletValueNgn + $ngxValueNgn + ($globalValueUsd * $FX_RATE) + ($cryptoValueUsd * $FX_RATE) + $fixedIncomeNgn;

        return [
            'success' => true,
            'trading_mode' => $user->trading_mode,
            'wallet_balance' => (float) $walletValueNgn,
            'ngx_value' => (float) $ngxValueNgn,
            'fixed_income_value' => (float) $fixedIncomeNgn,
            'global_stocks_value_usd' => (float) $globalValueUsd,
            'crypto_value_usd' => (float) $cryptoValueUsd,
            'holdings' => $holdings,
            'total_equity' => (float) $totalEquityNgn,
            'portfolio_distribution' => [
                ['label' => 'Wallet', 'value' => $walletValueNgn / $FX_RATE],
                ['label' => 'NGX', 'value' => $ngxValueNgn / $FX_RATE],
                ['label' => 'Global', 'value' => $globalValueUsd],
                ['label' => 'Crypto', 'value' => $cryptoValueUsd],
                ['label' => 'Fixed Income', 'value' => $fixedIncomeNgn / $FX_RATE],
            ],
        ];
    }
}
