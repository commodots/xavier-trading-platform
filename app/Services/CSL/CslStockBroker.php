<?php

namespace App\Services\CSL;

use App\Services\Stocks\Contracts\StockBroker;
use RuntimeException;

class CslStockBroker implements StockBroker
{
    public function __construct(
        protected CslStockClient $st,
        protected CslTradeXClient $xt
    ) {}

    /**
     * Place a buy order through CSL-XT.
     */
    public function buy(array $data): array
    {
        $payload = $this->buildOrderPayload($data);

        $response = $this->xt->buyOrder(
            $payload
        );

        return $this->normalizeOrderResponse(
            $response,
            'buy'
        );
    }

    /**
     * Place a sell order through CSL-XT.
     */
    public function sell(array $data): array
    {
        $payload = $this->buildOrderPayload($data);

        $response = $this->xt->sellOrder(
            $payload
        );

        return $this->normalizeOrderResponse(
            $response,
            'sell'
        );
    }

    /**
     * Get a user's portfolio.
     *
     * At this stage the caller must provide the CSL
     * market account ID.
     */
    public function portfolio(int $userId): array
    {
        throw new RuntimeException(
            'CSL portfolio lookup requires a mapped CSL market account. '
            .'User ID: '.$userId
        );
    }

    /**
     * Get trade history.
     *
     * At this stage the caller must provide the CSL
     * market account ID and date range.
     */
    public function history(int $userId): array
    {
        throw new RuntimeException(
            'CSL trade history requires a mapped CSL market account. '
            .'User ID: '.$userId
        );
    }

    /**
     * Convert Xavier order data to CSL-XT format.
     */
    protected function buildOrderPayload(
        array $data
    ): array {

        $marketId = $data['market_id']
            ?? config('services.csl.market_id');

        $marketAccountId = $data['market_account_id']
            ?? null;

        $symbol = $data['symbol']
            ?? $data['symbol_code']
            ?? null;

        $quantity = $data['quantity']
            ?? $data['units']
            ?? null;

        $type = strtolower(
            $data['type'] ?? 'market'
        );

        if (! $marketId) {
            throw new RuntimeException(
                'CSL market_id is required.'
            );
        }

        if (! $marketAccountId) {
            throw new RuntimeException(
                'CSL market_account_id is required.'
            );
        }

        if (! $symbol) {
            throw new RuntimeException(
                'CSL symbol_code is required.'
            );
        }

        if (! $quantity || $quantity <= 0) {
            throw new RuntimeException(
                'Order quantity must be greater than zero.'
            );
        }

        $orderType = match ($type) {
            'market', 'm' => 'M',
            'limit', 'l' => 'L',

            default => throw new RuntimeException(
                "Unsupported order type: {$type}"
            ),
        };

        $payload = [
            'market_id' => (string) $marketId,
            'market_account_id' => (string) $marketAccountId,
            'symbol_code' => (string) $symbol,
            'order_quantity' => (int) $quantity,
            'order_type' => $orderType,
            'time_in_force' => strtoupper(
                $data['time_in_force'] ?? 'DAY'
            ),
        ];

        if ($orderType === 'L') {

            $limitPrice = $data['limit_price']
                ?? $data['price']
                ?? null;

            if ($limitPrice === null) {
                throw new RuntimeException(
                    'Limit price is required for a limit order.'
                );
            }

            $payload['limit_price'] = (int) $limitPrice;
        }

        if ($payload['time_in_force'] === 'GTD') {

            if (empty($data['expiry_date'])) {
                throw new RuntimeException(
                    'expiry_date is required when time_in_force is GTD.'
                );
            }

            $payload['expiry_date'] = $data['expiry_date'];
        }

        return $payload;
    }

    protected function normalizeOrderResponse(
        array $response,
        string $side
    ): array {

        $result = $response['result'] ?? [];

        $first = $result[0] ?? [];

        return [
            'provider' => 'csl',
            'provider_api' => 'xt',
            'side' => $side,

            'status' => $response['status']
                ?? 'failed',

            'provider_code' => $first['code']
                ?? null,

            'remarks' => $first['remarks']
                ?? null,

            'raw' => $response,
        ];
    }
}
