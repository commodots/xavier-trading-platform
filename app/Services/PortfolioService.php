<?php

namespace App\Services;

use App\Models\Trade;
use Illuminate\Support\Collection;

class PortfolioService
{
    public function getUserPortfolio(int $userId): Collection
    {
        $trades = Trade::where('user_id', $userId)
            ->where('status', 'open')
            ->with('order')
            ->get();

        $grouped = [];

        foreach ($trades as $trade) {
            // Standardize symbol resolution
            $symbol = $trade->order->symbol ?? explode('/', $trade->pair)[0] ?? 'UNKNOWN';
            $symbol = strtoupper($symbol);

            if (! isset($grouped[$symbol])) {
                $grouped[$symbol] = [
                    'symbol' => $symbol,
                    'name' => $trade->order->company ?? $symbol,
                    'category' => $this->resolveCategory($trade, $symbol),
                    'quantity' => 0.0,
                    'total_cost' => 0.0,
                ];
            }

            $qty = (float) $trade->quantity;
            $price = (float) ($trade->entry_price ?? $trade->price ?? 0);

            $grouped[$symbol]['quantity'] += $qty;
            $grouped[$symbol]['total_cost'] += ($qty * $price);
        }

        return collect($grouped)
            ->filter(function ($asset) {
                // Remove zero quantity or "dust" (quantity < 0.00000001)
                return $asset['quantity'] > 0.00000001;
            })
            ->map(function ($asset) {
                // Weighted Average Calculation with safety guard
                $asset['avg_price'] = $asset['quantity'] > 0
                    ? round($asset['total_cost'] / $asset['quantity'], 8)
                    : 0.0;

                return $asset;
            })
            ->values();
    }

    /**
     * Enrich portfolio holdings with real-time market prices and P/L.
     */
    public function attachMarketPrices(Collection $portfolio): Collection
    {
        $marketService = app(MarketService::class);

        // Fetch real FX Rate from cache/DB
        $FX_RATE = cache()->remember('usd_ngn_rate', 3600, function () {
            return \App\Models\FxRate::where('from_currency', 'USD')->where('to_currency', 'NGN')->latest()->value('effective_rate')
                // Fallback to any latest effective rate if specific pair not found, or a default
                ?? 1500;
        });

        return $portfolio->map(function ($asset) use ($marketService, $FX_RATE) {
            $currentPrice = (float) $marketService->quote($asset['symbol']);

            $asset['current_price'] = round($currentPrice, 8);
            $asset['market_price'] = round($currentPrice, 8);
            $asset['current_value'] = round($asset['quantity'] * $currentPrice, 8);
            $asset['total_value'] = $asset['current_value'];
            $asset['profit_loss'] = round($asset['current_value'] - $asset['total_cost'], 8);

            $isUsdAsset = in_array($asset['category'], ['crypto', 'foreign'], true);
            $asset['avg_price_ngn'] = round($isUsdAsset ? $asset['avg_price'] * $FX_RATE : $asset['avg_price'], 2);
            $asset['total_value_ngn'] = round($isUsdAsset ? $asset['current_value'] * $FX_RATE : $asset['current_value'], 2);

            return $asset;
        });
    }

    protected function resolveCategory(Trade $trade, string $symbol): string
    {
        // Use market from order if available
        if ($trade->order && $trade->order->market === 'CRYPTO') {
            return 'crypto';
        }

        // Fallback to symbol/pair check
        $isCrypto = app(MarketService::class)->isCryptoPair($trade->pair ?? $symbol);

        return $isCrypto ? 'crypto' : 'foreign';
    }
}
