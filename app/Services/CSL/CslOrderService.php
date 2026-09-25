<?php

namespace App\Services\CSL;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\Stocks\Exceptions\ProviderRequestException;
use RuntimeException;

class CslOrderService
{
    public function __construct(
        protected CslTradeXClient $xt,
        protected CslOrderReconciliationService $reconciliation
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

        $previousStatus = $order->status;

        $order->update([
            'status' => 'cancel_requested',
            'provider_cancellation_status' => 'requested',
            'provider_cancel_requested_at' => now(),
        ]);

        ActivityLog::log($order->user_id, 'stock_order_cancel_requested', [
            'order_id' => $order->id,
            'provider' => 'csl',
            'provider_order_id' => $order->provider_order_id,
            'provider_client_reference' => $order->provider_client_reference,
        ]);

        try {
            $response = $this->xt->cancelOrder(
                $order->provider_request['market_id']
                    ?? 'NGX1',
                $order->provider_market_account_id,
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

        $result = $response['result'][0] ?? $response;
        $status = strtolower((string) ($result['code'] ?? $result['status'] ?? ''));

        if ($status !== '' && ! in_array($status, ['a', 'p', 'accepted', 'pending', 'success'], true)) {
            $order->update([
                'status' => $previousStatus,
                'provider_cancellation_status' => 'rejected',
            ]);
            throw new RuntimeException('CSL rejected the order cancellation request.');
        }

        $order->update([
            'provider_cancellation_status' => 'unknown',
        ]);

        $this->reconciliation->reconcile($order->provider_market_account_id);

        return $response;
    }
}
