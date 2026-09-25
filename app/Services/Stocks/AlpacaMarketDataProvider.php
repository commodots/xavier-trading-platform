<?php

namespace App\Services\Stocks;

use App\Providers\AlpacaProvider;
use App\Services\Stocks\Contracts\MarketDataProvider;

/**
 * Global market data through Alpaca, selected by market.
 *
 * NGX -> CslMarketDataProvider, GLOBAL -> AlpacaMarketDataProvider, so no
 * provider-specific data call is ever hard-coded in a controller or view.
 */
class AlpacaMarketDataProvider implements MarketDataProvider
{
    public function __construct(
        protected AlpacaProvider $provider
    ) {}

    public function quote(string $symbol): array
    {
        $details = $this->provider->quoteDetails($symbol);

        $price = (float) ($details['price'] ?? 0);
        $previousClose = (float) ($details['previous_close'] ?? 0);

        $change = $price - $previousClose;
        $changePercent = $previousClose > 0
            ? ($change / $previousClose) * 100
            : 0.0;

        return [
            'symbol' => strtoupper($symbol),
            'name' => strtoupper($symbol),
            'market' => 'GLOBAL',
            'price' => $price,
            'previous_close' => $previousClose,
            'open' => null,
            'high' => null,
            'low' => null,
            'change' => round($change, 4),
            'change_percent' => round($changePercent, 2),
            'bid_price' => null,
            'bid_quantity' => null,
            'offer_price' => null,
            'offer_quantity' => null,
            'volume' => null,
            'value' => null,
            'timestamp' => $details['timestamp'] ?? now()->toISOString(),
            'provider' => 'alpaca',
        ];
    }

    public function historical(
        string $symbol,
        string $range = '7d'
    ): array {
        $days = $this->parseRange($range);

        try {
            $response = $this->provider->bars(
                $symbol,
                $days
            );
        } catch (\Throwable $exception) {
            $response = [];
        }

        return $this->normaliseBars($symbol, $range, $response);
    }

    protected function parseRange(string $range): int
    {
        return match (strtolower($range)) {
            '1d' => 1,
            '7d' => 7,
            '14d' => 14,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 7,
        };
    }

    protected function normaliseBars(
        string $symbol,
        string $range,
        array $response
    ): array {
        $bars = $response['bars']
            ?? $response['data']
            ?? $response
            ?? [];

        if (isset($bars['bar']) && is_array($bars['bar'])) {
            $bars = $bars['bar'];
        }

        if (! is_array($bars)) {
            $bars = [];
        }

        $points = [];

        foreach ($bars as $bar) {
            if (! is_array($bar)) {
                continue;
            }

            $points[] = [
                'date' => $bar['t'] ?? $bar['date'] ?? null,
                'open' => $this->floatOrNull($bar['o'] ?? $bar['open'] ?? null),
                'high' => $this->floatOrNull($bar['h'] ?? $bar['high'] ?? null),
                'low' => $this->floatOrNull($bar['l'] ?? $bar['low'] ?? null),
                'close' => $this->floatOrNull($bar['c'] ?? $bar['close'] ?? null),
                'volume' => $this->floatOrNull($bar['v'] ?? $bar['volume'] ?? null),
            ];
        }

        return [
            'symbol' => strtoupper($symbol),
            'market' => 'GLOBAL',
            'range' => $range,
            'data' => $points,
            'provider' => 'alpaca',
        ];
    }

    protected function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
