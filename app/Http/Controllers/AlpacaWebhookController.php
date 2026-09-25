<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Stocks\AlpacaOrderReconciliationService;
use Illuminate\Http\Request;

/**
 * Alpaca order events.
 *
 * The webhook never creates a trade from a cumulative quantity. It hands the
 * payload to the reconciliation service, which computes the newly filled delta
 * and creates an idempotent trade, so a 0.5 fill followed by a 1.0 fill records
 * 0.5 + 0.5 rather than 0.5 + 1.0.
 */
class AlpacaWebhookController extends Controller
{
    public function handle(
        Request $request,
        AlpacaOrderReconciliationService $reconciliation
    ) {
        $event = $request->all();

        $payload = $event['order'] ?? $event;
        $eventType = strtolower((string) (
            $event['event_type']
            ?? $event['event']
            ?? $payload['status']
            ?? ''
        ));

        $orderId = $payload['id']
            ?? $payload['order_id']
            ?? $event['order_id']
            ?? null;

        if (! $orderId) {
            return response()->json(['ignored']);
        }

        $order = Order::query()
            ->where('provider', 'alpaca')
            ->where(function ($query) use ($orderId) {
                $query->where('provider_order_id', $orderId)
                    ->orWhere('alpaca_order_id', $orderId);
            })
            ->first();

        if (! $order) {
            // Fall back to the Xavier client reference when the id is unknown.
            $clientOrderId = $payload['client_order_id'] ?? null;

            $order = $clientOrderId
                ? Order::query()
                    ->where('provider', 'alpaca')
                    ->where('provider_client_reference', $clientOrderId)
                    ->first()
                : null;
        }

        if (! $order) {
            return response()->json(['ignored']);
        }

        /*
         * Terminal events (cancel/expire/reject) are the provider's word on the
         * final state, so they go through the same delta logic: whatever did
         * execute is booked, and only the remainder is released.
         */
        $terminalEvents = ['canceled', 'cancelled', 'expired', 'rejected', 'replaced'];

        if (in_array($eventType, $terminalEvents, true)) {
            $payload['status'] = $payload['status'] ?? $eventType;

            $reconciliation->applyProviderOrder($order, $payload);

            return response()->json(['ok' => true]);
        }

        if (! isset($payload['filled_qty'])) {
            return response()->json(['ok' => true]);
        }

        $reconciliation->applyProviderOrder($order, $payload);

        return response()->json(['ok' => true]);
    }
}
