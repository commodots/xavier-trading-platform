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

        $rows = $this->extract(
            $response,
            'GetCRXTMarketStockPrice'
        );

        $row = $rows[0] ?? [];

        return [
            'symbol' => $row['symbol_code']
                ?? $symbol,

            'name' => $row['symbol_description']
                ?? null,

            'market' => $row['market_code']
                ?? 'NGX',

            'price' => $row['current_price']
                ?? null,

            'previous_close' => $row['previous_close_price']
                ?? null,

            'open' => $row['opening_price']
                ?? null,

            'high' => $row['high_price']
                ?? null,

            'low' => $row['low_price']
                ?? null,

            'change' => $row['price_difference_today']
                ?? null,

            'change_percent' => $row['percent_difference_today']
                ?? null,

            'bid_price' => $row['top_bid_price']
                ?? null,

            'bid_quantity' => $row['top_bid_quantity']
                ?? null,

            'offer_price' => $row['top_offer_price']
                ?? null,

            'offer_quantity' => $row['top_offer_quantity']
                ?? null,

            'volume' => $row['traded_volume']
                ?? null,

            'value' => $row['traded_value']
                ?? null,

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
}
