<?php

namespace App\Services\CSL;

use App\Services\Stocks\Contracts\MarketDataProvider;
use Carbon\Carbon;

class CslMarketDataProvider implements MarketDataProvider
{
    public function __construct(
        protected CslTradeXClient $xt,
        protected CslStockClient $st
    ) {}

    public function quote(
        string $symbol
    ): array {

        $response = $this->xt->stockPrice(
            $symbol
        );

        $row = $this->extractQuoteRow(
            $response,
            $symbol
        );

        return [
            'symbol' => $row['symbol']
                ?? $row['symbol_code']
                ?? $symbol,

            'name' => $row['name']
                ?? $row['symbol_description']
                ?? null,

            'market' => $row['market']
                ?? $row['market_code']
                ?? 'NGX',

            'price' => $this->floatOrNull($row['price'] ?? $row['current_price'] ?? null),

            'previous_close' => $this->floatOrNull($row['previous_close'] ?? $row['previous_close_price'] ?? null),

            'open' => $this->floatOrNull($row['open'] ?? $row['opening_price'] ?? null),

            'high' => $this->floatOrNull($row['high'] ?? $row['high_price'] ?? null),

            'low' => $this->floatOrNull($row['low'] ?? $row['low_price'] ?? null),

            'change' => $this->floatOrNull($row['change'] ?? $row['price_difference_today'] ?? null),

            'change_percent' => $this->floatOrNull($row['change_percent'] ?? $row['percent_difference_today'] ?? null),

            'bid_price' => $this->floatOrNull($row['bid_price'] ?? $row['top_bid_price'] ?? null),

            'bid_quantity' => $this->intOrNull($row['bid_quantity'] ?? $row['top_bid_quantity'] ?? null),

            'offer_price' => $this->floatOrNull($row['offer_price'] ?? $row['top_offer_price'] ?? null),

            'offer_quantity' => $this->intOrNull($row['offer_quantity'] ?? $row['top_offer_quantity'] ?? null),

            'volume' => $this->intOrNull($row['volume'] ?? $row['traded_volume'] ?? null),

            'value' => $this->intOrNull($row['value'] ?? $row['traded_value'] ?? null),

            'provider' => 'csl',
        ];
    }

    public function historical(
        string $symbol,
        string $range = '7d'
    ): array {

        $days = $this->parseRange(
            $range
        );

        $end = Carbon::today();

        $start = $end->copy()
            ->subDays($days);

        $response = $this->st->stockByDate(
            $symbol,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        return $this->extract(
            $response,
            'GetCRSTStockByDate'
        );
    }

    protected function parseRange(
        string $range
    ): int {

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

    protected function extract(
        array $response,
        string $key
    ): array {

        /**
         * CSL has slightly different response wrappers
         * between ST and XT endpoints.
         */
        if (isset($response[$key])
            && is_array($response[$key])) {

            return $response[$key];
        }

        if (isset($response['data'])
            && is_array($response['data'])) {

            if (isset($response['data'][$key])
                && is_array($response['data'][$key])) {
                return $response['data'][$key];
            }

            return $response['data'];
        }

        if (
            isset($response['result'])
            && is_array($response['result'])
        ) {

            foreach ($response['result'] as $item) {

                if (
                    isset($item[$key])
                    && is_array($item[$key])
                ) {
                    return $item[$key];
                }
            }
        }

        return [];
    }

    protected function extractQuoteRow(array $response, string $symbol): array
    {
        $keys = [
            'GetCRXTMarketStockPrice',
            'GetCRXTMarketStockPrices',
            'result',
            'data',
        ];

        foreach ($keys as $key) {
            if (! isset($response[$key])) {
                continue;
            }

            $value = $response[$key];

            if (is_array($value)) {
                if (isset($value[0]) && is_array($value[0])) {
                    return $value[0];
                }

                if ($value !== [] && array_key_exists('symbol', $value)) {
                    return $value;
                }
            }
        }

        if (isset($response['data']) && is_array($response['data']) && isset($response['data']['symbol'])) {
            return $response['data'];
        }

        return [
            'symbol' => $symbol,
        ];
    }

    protected function floatOrNull(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : $value;
    }

    protected function intOrNull(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $float = (float) $value;

            return floor($float) === $float ? (int) $float : $float;
        }

        return $value;
    }
}
