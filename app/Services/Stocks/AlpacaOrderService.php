<?php

namespace App\Services\Stocks;

use App\Models\Order;
use App\Providers\AlpacaProvider;
use RuntimeException;

/**
 * Cancellation lifecycle for Global stock orders.
 *
 * A cancel is a provider state transition, never a local status flip: funds
 * are only released once reconciliation confirms the provider cancelled the
 * order (and only for the quantity that did not execute).
 */
class AlpacaOrderService
{
    public function __construct(
        protected AlpacaProvider $provider,
        protected AlpacaOrderReconciliationService $reconciliation
    ) {}

    public function cancel(Order $order): array
    {
        if ($order->provider !== 'alpaca' || ! $order->provider_order_id) {
            throw new RuntimeException('Order is not an Alpaca order.');
        }

        $order->update([
            'provider_cancellation_status' => 'requested',
            'provider_cancel_requested_at' => now(),
        ]);

        try {
            $response = $this->provider->cancelOrder(
                $order->provider_order_id
            );
        } catch (\Throwable $exception) {
            /*
             * The request may still have reached Alpaca. Record the ambiguity
             * instead of assuming failure, and never release funds here.
             */
            $order->update([
                'provider_cancellation_status' => 'unknown',
            ]);

            throw $exception;
        }

        $status = strtolower((string) ($response['status'] ?? ''));

        if (in_array($status, ['rejected', 'error'], true)) {
            $order->update([
                'provider_cancellation_status' => 'rejected',
            ]);

            throw new RuntimeException(
                'Alpaca rejected the order cancellation request.'
            );
        }

        /*
         * Accepted-but-unconfirmed: reconciliation decides the final outcome.
         */
        $order->update([
            'provider_cancellation_status' => 'unknown',
        ]);

        $this->reconciliation->reconcile();

        return $response;
    }
}
