<?php

namespace App\Services\Stocks;

use App\Providers\AlpacaProvider;
use App\Services\Stocks\Contracts\StockBroker;
use RuntimeException;

/**
 * Provider-neutral adapter that translates Xavier's common stock order shape
 * into Alpaca's order schema, mirroring CslStockBroker.
 */
class AlpacaStockBroker implements StockBroker
{
    public function __construct(
        protected AlpacaProvider $provider
    ) {}

    public function buy(array $data): array
    {
        return $this->submit('buy', $data);
    }

    public function sell(array $data): array
    {
        return $this->submit('sell', $data);
    }

    protected function submit(string $side, array $data): array
    {
        if (empty($data['symbol'])) {
            throw new RuntimeException('Symbol is required.');
        }

        $quantity = (float) ($data['quantity'] ?? 0);

        if ($quantity <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }

        $payload = [
            'symbol' => strtoupper((string) $data['symbol']),
            'qty' => $quantity,
            'type' => strtolower($data['type'] ?? 'market'),
            'time_in_force' => strtolower($data['time_in_force'] ?? 'day'),
        ];

        if ($payload['type'] === 'limit') {
            $payload['limit_price'] = $data['limit_price']
                ?? $data['price']
                ?? null;

            if ($payload['limit_price'] === null) {
                throw new RuntimeException('Limit price is required.');
            }
        }

        if ($payload['type'] === 'stop') {
            $payload['stop_price'] = $data['stop_price'] ?? null;

            if ($payload['stop_price'] === null) {
                throw new RuntimeException('Stop price is required.');
            }
        }

        /*
         * Alpaca represents a bracket as a market order with order_class set;
         * the take-profit / stop-loss legs ride along on the same payload.
         */
        if (($data['type'] ?? '') === 'bracket') {
            if (! isset($data['take_profit'], $data['stop_loss'])) {
                throw new RuntimeException(
                    'Bracket orders require both take profit and stop loss.'
                );
            }

            $payload['type'] = 'market';
            $payload['order_class'] = 'bracket';
            $payload['take_profit'] = [
                'limit_price' => (float) $data['take_profit'],
            ];
            $payload['stop_loss'] = [
                'stop_price' => (float) $data['stop_loss'],
            ];
        }

        /*
         * Correlate the provider order with the Xavier order so that
         * reconciliation can discover it even if the submit response is
         * ambiguous.
         */
        $payload['client_order_id'] = $data['client_reference']
            ?? $data['xavier_client_reference']
            ?? null;

        $response = $side === 'buy'
            ? $this->provider->buy($payload)
            : $this->provider->sell($payload);

        return [
            'provider' => 'alpaca',
            'side' => $side,
            'status' => $this->extractStatus($response),
            'provider_order_id' => $this->extractOrderId($response),
            'remarks' => $this->extractRemarks($response),
            'request' => $payload,
            'response' => $response,
        ];
    }

    /**
     * Normalise an Alpaca order status into Xavier's common vocabulary.
     *
     * "accepted" and "new" both mean the provider has taken the order but has
     * not executed it yet — it must never be reported as filled here.
     */
    protected function extractStatus(array $response): string
    {
        $status = strtolower((string) ($response['status'] ?? ''));

        return match ($status) {
            'accepted',
            'new',
            'pending_new',
            'pending_replace',
            'pending_cancel',
            'accepted_for_bidding' => 'accepted',

            'partially_filled' => 'partially_filled',

            'filled' => 'filled',

            'canceled',
            'cancelled',
            'expired',
            'replaced',
            'rejected',
            'rejected_for_day' => 'rejected',

            default => 'pending',
        };
    }

    protected function extractOrderId(array $response): ?string
    {
        $orderId = $response['id'] ?? null;

        return filled($orderId) ? (string) $orderId : null;
    }

    protected function extractRemarks(array $response): ?string
    {
        return $response['reject_reason']
            ?? $response['status']
            ?? null;
    }

    public function portfolio(int $userId): array
    {
        return $this->provider->portfolio($userId);
    }

    public function history(int $userId): array
    {
        return $this->provider->history($userId);
    }
}
