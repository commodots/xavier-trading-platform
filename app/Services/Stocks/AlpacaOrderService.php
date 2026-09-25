<?php

namespace App\Services\Stocks;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Providers\AlpacaProvider;
use App\Services\Stocks\Exceptions\ProviderRequestException;
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

        if (in_array($order->provider_cancellation_status, [
            'requested', 'unknown', 'confirmed',
        ], true)) {
            return [
                'id' => $order->provider_order_id,
                'status' => $order->provider_cancellation_status === 'confirmed'
                    ? 'canceled'
                    : 'cancel_requested',
            ];
        }

        if (! in_array(
            $order->status,
            ['open', 'pending', 'partially_filled', 'cancel_requested'],
            true
        )) {
            throw new RuntimeException(
                'This Alpaca order cannot be canceled in its current state.'
            );
        }

        $previousStatus = $order->status;

        $order->update([
            'status' => 'cancel_requested',
            'provider_cancellation_status' => 'requested',
            'provider_cancel_requested_at' => now(),
        ]);

        ActivityLog::log($order->user_id, 'stock_order_cancel_requested', [
            'order_id' => $order->id,
            'provider' => 'alpaca',
            'provider_order_id' => $order->provider_order_id,
            'provider_client_reference' => $order->provider_client_reference,
        ]);

        try {
            $response = $this->provider->cancelOrder(
                $order->provider_order_id
            );
        } catch (\Throwable $exception) {
            $rejected = $exception instanceof ProviderRequestException
                && ! $exception->ambiguous;

            $order->update([
                'status' => $rejected ? $previousStatus : $order->status,
                'provider_cancellation_status' => $rejected
                    ? 'rejected'
                    : 'unknown',
            ]);

            throw $exception;
        }

        $status = strtolower((string) ($response['status'] ?? ''));

        if (in_array($status, ['rejected', 'error'], true)) {
            $order->update([
                'status' => $previousStatus,
                'provider_cancellation_status' => 'rejected',
            ]);

            throw new RuntimeException(
                'Alpaca rejected the order cancellation request.'
            );
        }

        /**
         * Accepted-but-unconfirmed: reconciliation decides the final outcome.
         */
        $order->update([
            'provider_cancellation_status' => 'unknown',
        ]);

        $this->reconciliation->reconcile();

        return $response;
    }
}
