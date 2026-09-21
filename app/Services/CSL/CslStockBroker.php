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

    public function buy(array $data): array
    {
        $this->guardLiveTrading();

        return $this->submit(
            'buy',
            $data
        );
    }

    public function sell(array $data): array
    {
        $this->guardLiveTrading();

        return $this->submit(
            'sell',
            $data
        );
    }

    protected function submit(
        string $side,
        array $data
    ): array {

        $payload = [
            'market_id' => $data['market_id'],

            'market_account_id' => $data['market_account_id'],

            'symbol_code' => $data['symbol'],

            'order_quantity' => (int) $data['quantity'],

            'order_type' => $this->mapOrderType(
                $data['type'] ?? 'market'
            ),

            'time_in_force' => strtoupper(
                $data['time_in_force']
                ?? 'DAY'
            ),
        ];

        if ($payload['order_type'] === 'L') {

            $payload['limit_price'] =
                $data['limit_price']
                ?? $data['price']
                ?? null;

            if ($payload['limit_price'] === null) {
                throw new RuntimeException(
                    'Limit price is required.'
                );
            }
        }

        if ($payload['time_in_force'] === 'GTD') {

            if (empty($data['expiry_date'])) {
                throw new RuntimeException(
                    'expiry_date is required for GTD.'
                );
            }

            $payload['expiry_date'] =
                $data['expiry_date'];
        }

        $response = $side === 'buy'
            ? $this->xt->buyOrder($payload)
            : $this->xt->sellOrder($payload);

        return [
            'provider' => 'csl',
            'side' => $side,
            'status' => $this->extractStatus($response),
            'provider_order_id' => $this->extractOrderId($response),
            'remarks' => $this->extractRemarks($response),
            'request' => $payload,
            'response' => $response,
        ];
    }

    protected function guardLiveTrading(): void
    {
        $enabled = filter_var(
            config('services.csl.live_trading_enabled', false),
            FILTER_VALIDATE_BOOL
        );

        $mock = filter_var(
            config('services.csl.mock', true),
            FILTER_VALIDATE_BOOL
        );

        /*
         * Safety rule: the broker always refuses live trading unless the
         * activation flag is on. Mock mode is the exception because no real
         * order leaves Xavier in that case.
         */
        if (! $enabled && ! $mock) {
            throw new RuntimeException('CSL live trading is disabled');
        }
    }

    protected function mapOrderType(
        string $type
    ): string {

        return match (strtolower($type)) {
            'market', 'm' => 'M',
            'limit', 'l' => 'L',

            default => throw new RuntimeException(
                "Unsupported order type: {$type}"
            ),
        };
    }

    protected function extractStatus(
        array $response
    ): string {

        $row = $response['result'][0]
            ?? [];

        $code = strtoupper(
            (string) (
                $row['code'] ?? ''
            )
        );

        return match ($code) {
            'A' => 'accepted',
            'P' => 'pending',
            default => 'rejected',
        };
    }

    protected function extractOrderId(
        array $response
    ): ?string {

        $row = $response['result'][0]
            ?? [];

        $orderId = $row['order_identifier']
            ?? $row['order_id']
            ?? null;

        if (filled($orderId)) {
            return (string) $orderId;
        }

        /*
         * XT submit responses may nest the accepted order number inside a
         * "reference" object instead of exposing a flat order id.
         */
        $reference = $row['reference']
            ?? null;

        if (is_array($reference)) {

            $nested = $reference['OrderNo']
                ?? $reference['order_no']
                ?? $reference['order_id']
                ?? null;

            if (filled($nested)) {
                return (string) $nested;
            }
        }

        return null;
    }

    protected function extractRemarks(
        array $response
    ): ?string {

        $row = $response['result'][0]
            ?? [];

        return $row['remarks']
            ?? null;
    }

    public function portfolio(
        int $userId
    ): array {
        throw new RuntimeException(
            'Use CslAccountService to resolve CSL account first.'
        );
    }

    public function history(
        int $userId
    ): array {
        throw new RuntimeException(
            'Use CslAccountService to resolve CSL account first.'
        );
    }
}
