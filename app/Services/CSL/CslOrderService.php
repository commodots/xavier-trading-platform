<?php

namespace App\Services\CSL;

use App\Models\Order;
use RuntimeException;

class CslOrderService
{
    public function __construct(
        protected CslTradeXClient $xt
    ) {}

    public function cancel(
        Order $order
    ): array {

        if (
            $order->provider !== 'csl'
            || ! $order->provider_order_id
        ) {
            throw new RuntimeException(
                'Order is not a CSL order.'
            );
        }

        if (! $order->provider_market_account_id) {
            throw new RuntimeException(
                'CSL market account is missing.'
            );
        }

        $response = $this->xt->cancelOrder(
            $order->provider_request['market_id']
                ?? 'NGX1',
            $order->provider_market_account_id,
            $order->provider_order_id
        );

        $result = $response['result'][0] ?? $response;
        $status = strtolower((string) ($result['code'] ?? $result['status'] ?? ''));

        if ($status !== '' && ! in_array($status, ['a', 'p', 'accepted', 'pending', 'success'], true)) {
            throw new RuntimeException('CSL rejected the order cancellation request.');
        }

        return $response;
    }
}
