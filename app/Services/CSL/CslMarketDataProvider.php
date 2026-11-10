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

        return $this->normalizeQuote($row, $symbol);
    }

    public function stockPrices(): array
    {
        return $this->normalizeQuotes(
            $this->xt->stockPrices(),
            'GetCRXTMarketStockPrices'
        );
    }

    public function topGainers(): array
    {
        return $this->normalizeQuotes(
            $this->xt->topGainers(),
            'GetCRXTMarketTopGainers'
        );
    }

    public function topLosers(): array
    {
        return $this->normalizeQuotes(
            $this->xt->topLosers(),
            'GetCRXTMarketTopLosers'
        );
    }

    public function orderBook(string $symbol): array
    {
        return [
            'bids' => $this->normalizeLevels(
                $this->xt->topBids($symbol),
                'GetCRXTMarketTopBids'
            ),
            'offers' => $this->normalizeLevels(
                $this->xt->topOffers($symbol),
                'GetCRXTMarketTopOffers'
            ),
            'purchase_depth' => $this->normalizeLevels(
                $this->xt->marketDepthPurchase(),
                'GetCRXTMarketDepthPurchase'
            ),
            'sale_depth' => $this->normalizeLevels(
                $this->xt->marketDepthSale(),
                'GetCRXTMarketDepthSale'
            ),
        ];
    }

    public function historical(
        string $symbol,
        string $range = '7d',
        ?string $startDate = null,
        ?string $endDate = null
    ): array {

        $end = $endDate !== null
            ? Carbon::parse($endDate)
            : Carbon::today();

        $start = $startDate !== null
            ? Carbon::parse($startDate)
            : (strtolower($range) === 'all'
                ? Carbon::parse('1900-01-01')
                : $end->copy()->subDays($this->parseRange($range)));

        $response = $this->st->stockByDate(
            $symbol,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        return $this->extractRows(
            $response,
            'GetCRSTStockByDate'
        );
    }

    protected function normalizeQuote(array $row, string $symbol): array
    {
        $change = $this->floatOrNull(
            $row['change']
                ?? $row['price_difference_today']
                ?? null
        );
        $changePercent = $this->floatOrNull(
            $row['change_percent']
                ?? $row['percent_difference_today']
                ?? null
        );
        $previousClose = $this->floatOrNull(
            $row['previous_close']
                ?? $row['previous_close_price']
                ?? null
        );

        if ($changePercent === null
            && is_numeric($change)
            && is_numeric($previousClose)
            && (float) $previousClose !== 0.0) {
            $changePercent = (float) $change / (float) $previousClose * 100;
        }

        return [
            'symbol' => $row['symbol']
                ?? $row['symbol_id']
                ?? $row['symbol_code']
                ?? $symbol,

            'name' => $row['name']
                ?? $row['symbol_description']
                ?? $symbol,

            'market' => $row['market']
                ?? $row['market_code']
                ?? $row['market_id']
                ?? 'NGX',

            'price' => $this->floatOrNull($row['price'] ?? $row['current_price'] ?? null),

            'previous_close' => $previousClose,

            'open' => $this->floatOrNull($row['open'] ?? $row['opening_price'] ?? null),

            'high' => $this->floatOrNull($row['high'] ?? $row['high_price'] ?? null),

            'low' => $this->floatOrNull($row['low'] ?? $row['low_price'] ?? null),

            'change' => $change,

            'change_percent' => $changePercent,

            'bid_price' => $this->floatOrNull($row['bid_price'] ?? $row['top_bid_price'] ?? null),

            'bid_quantity' => $this->intOrNull($row['bid_quantity'] ?? $row['top_bid_quantity'] ?? null),

            'offer_price' => $this->floatOrNull($row['offer_price'] ?? $row['top_offer_price'] ?? null),

            'offer_quantity' => $this->intOrNull($row['offer_quantity'] ?? $row['top_offer_quantity'] ?? null),

            'volume' => $this->intOrNull($row['volume'] ?? $row['traded_volume'] ?? null),

            'value' => $this->intOrNull($row['value'] ?? $row['traded_value'] ?? null),

            'provider' => 'csl',
        ];
    }

    protected function parseRange(
        string $range
    ): int {

        return match (strtolower($range)) {
            '1d' => 1,
            '1w' => 7,
            '7d' => 7,
            '1m' => 30,
            '14d' => 14,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,

            default => 7,
        };
    }

    /**
     * Unwrap a CSL payload for the given endpoint key.
     *
     * CSL uses slightly different response wrappers between ST, XT and
     * the wrapped "result" envelope, so they are normalised here. Public
     * so csl:diagnose can read the market status without duplicating it.
     */
    public function extract(
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

    protected function normalizeQuotes(array $response, string $key): array
    {
        return collect($this->extractRows($response, $key))
            ->map(fn (array $row) => $this->normalizeQuote(
                $row,
                (string) ($row['symbol_code'] ?? $row['symbol_id'] ?? $row['symbol'] ?? '')
            ))
            ->filter(fn (array $quote) => $quote['symbol'] !== '')
            ->values()
            ->all();
    }

    protected function extractRows(array $response, string $key): array
    {
        $rows = $this->extract($response, $key);

        if ($rows !== []) {
            return array_values(array_filter(
                array_is_list($rows) ? $rows : [$rows],
                'is_array'
            ));
        }

        foreach (['result', 'data'] as $containerKey) {
            $container = $response[$containerKey] ?? null;

            if (! is_array($container)) {
                continue;
            }

            if (array_is_list($container)) {
                foreach ($container as $item) {
                    if (is_array($item) && isset($item[$key]) && is_array($item[$key])) {
                        return array_values(array_filter(
                            $item[$key],
                            'is_array'
                        ));
                    }
                }

                if ($container !== [] && is_array($container[0])) {
                    return $container;
                }
            }
        }

        return [];
    }

    protected function normalizeLevels(array $response, string $key): array
    {
        return collect($this->extractRows($response, $key))
            ->map(fn (array $row) => [
                'price' => $this->floatOrNull(
                    $row['price']
                        ?? $row['bid_price']
                        ?? $row['offer_price']
                        ?? $row['order_price']
                        ?? $row['top_bid_price']
                        ?? $row['top_offer_price']
                        ?? null
                ),
                'quantity' => $this->intOrNull(
                    $row['quantity']
                        ?? $row['order_quantity']
                        ?? $row['bid_quantity']
                        ?? $row['offer_quantity']
                        ?? $row['top_bid_quantity']
                        ?? $row['top_offer_quantity']
                        ?? null
                ),
            ])
            ->values()
            ->all();
    }

    protected function extractQuoteRow(array $response, string $symbol): array
    {
        $rows = $this->extractRows(
            $response,
            'GetCRXTMarketStockPrice'
        );

        if (isset($rows[0])) {
            return $rows[0];
        }

        foreach (['data', 'result'] as $containerKey) {
            $container = $response[$containerKey] ?? null;

            if (is_array($container)) {
                foreach ($container as $item) {
                    if (is_array($item) && (
                        array_key_exists('symbol', $item)
                        || array_key_exists('symbol_code', $item)
                        || array_key_exists('symbol_id', $item)
                    )) {
                        return $item;
                    }
                }
            }
        }

        if (array_key_exists('symbol', $response)
            || array_key_exists('symbol_code', $response)
            || array_key_exists('symbol_id', $response)) {
            return $response;
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
