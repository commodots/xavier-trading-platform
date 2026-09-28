<?php

namespace App\Services\Stocks;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderSyncLog;
use App\Models\Trade;
use App\Models\Wallet;
use App\Providers\AlpacaProvider;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile local Global stock orders against Alpaca.
 *
 * Mirrors CslOrderReconciliationService: discovery by provider order id first,
 * delta-based fills and idempotent trade creation. A fill is only ever recorded
 * for the newly executed quantity, never the cumulative total, so a 0.5 fill
 * followed by a 1.5 fill books 0.5 + 1.0 rather than 0.5 + 1.5.
 */
class AlpacaOrderReconciliationService
{
    public function __construct(
        protected AlpacaProvider $provider
    ) {}

    /**
     * @return array{orders: int, trades: int}
     */
    public function reconcile(): array
    {
        $providerOrders = $this->extractOrders($this->provider->orders());

        /**
         * Anything still live, plus anything whose outcome is not established
         * yet (a submit that timed out, or an order the provider filled before
         * we stored the response). Settled orders are never revisited.
         */
        $localOrders = Order::query()
            ->where('provider', 'alpaca')
            ->where(function ($query) {
                $query->whereIn('status', ['open', 'partially_filled', 'pending'])
                    ->orWhereIn('reconciliation_status', ['pending', 'unknown']);
            })
            ->get();

        $updated = 0;
        $trades = 0;

        foreach ($localOrders as $order) {
            $providerOrder = $this->matchProviderOrder($order, $providerOrders);

            if (! $providerOrder) {
                $this->markUnmatched($order);

                continue;
            }

            if ($this->applyProviderOrder($order, $providerOrder)) {
                $trades++;
            }

            $updated++;
        }

        return ['orders' => $updated, 'trades' => $trades];
    }

    /**
     * Apply one provider order payload to its local counterpart.
     *
     * Public so the webhook reuses the same delta logic instead of
     * reimplementing fill handling.
     *
     * @return bool whether a new fill was booked
     */
    public function applyProviderOrder(
        Order $order,
        array $providerOrder
    ): bool {
        return DB::transaction(function () use ($order, $providerOrder): bool {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $previouslyFilled = (float) $order->filled_quantity;
            $providerFilled = min(
                $this->number($providerOrder['filled_qty'] ?? 0),
                $this->number($order->quantity)
            );
            $providerFilled = max($providerFilled, $previouslyFilled);
            $newlyFilled = max(0, $providerFilled - $previouslyFilled);
            $fillPrice = $this->number(
                $providerOrder['filled_avg_price']
                ?? $providerOrder['limit_price']
            ) ?: (float) $order->price;
            $providerOrderId = $this->stringValue($providerOrder['id'] ?? null);
            $status = $this->mapStatus(
                (string) ($providerOrder['status'] ?? '')
            );
            $wasCancellationConfirmed =
                $order->provider_cancellation_status === 'confirmed';
            $wasTerminal = in_array(
                $order->status,
                ['canceled', 'rejected', 'failed'],
                true
            );

            $updates = [
                'status' => $status,
                'filled_quantity' => $providerFilled,
                'provider_response' => $providerOrder,
                'last_reconciled_at' => now(),
                'reconciliation_status' => 'matched',
            ];

            if ($providerOrderId) {
                $updates['provider_order_id'] = $providerOrderId;
                $updates['alpaca_order_id'] = $providerOrderId;
            }

            $canceled = $status === 'canceled';
            $unsuccessfulTerminal = in_array(
                $status,
                ['canceled', 'rejected', 'failed'],
                true
            );

            if ($canceled) {
                $updates['provider_cancellation_status'] = 'confirmed';
            }

            $order->update($updates);
            $bookedFill = false;

            if ($newlyFilled > 0) {
                $bookedFill = $this->recordTrade(
                    $order,
                    $newlyFilled,
                    $providerFilled,
                    $fillPrice,
                    $providerOrder,
                    $providerOrderId
                );

                if ($bookedFill) {
                    $this->applyLocalFill($order, $newlyFilled, $fillPrice);
                }
            }

            // A repeated terminal webhook must not release the same remainder.
            if ($unsuccessfulTerminal
                && ! $wasTerminal
                && ! $wasCancellationConfirmed) {
                $this->releaseRemainingReservation($order, $providerFilled);
            }

            $this->recordAudit($order, $status, $bookedFill);

            return $bookedFill;
        });
    }

    /**
     * Book the newly executed quantity as an idempotent trade.
     *
     * The Xavier reference embeds the cumulative provider fill, so replaying
     * the same provider payload can never double-book: the second attempt
     * collides on the same reference and is discarded.
     */
    protected function recordTrade(
        Order $order,
        float $newlyFilled,
        float $providerFilled,
        float $fillPrice,
        array $providerOrder,
        ?string $providerOrderId
    ): bool {
        $reference = 'ALPACA-FILL-'.$order->id.'-'
            .$this->numberString($providerFilled);

        $trade = Trade::firstOrCreate(
            [
                'provider' => 'alpaca',
                'reference' => $reference,
            ],
            [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'quantity' => $newlyFilled,
                'price' => $fillPrice,
                'amount' => $newlyFilled * $fillPrice,
                'settlement_status' => 'pending',
                'status' => 'pending',
                'is_settled' => false,
                'provider_order_id' => $providerOrderId,
                'provider_response' => $providerOrder,
                'provider_executed_at' => $providerOrder['filled_at'] ?? null,
            ]
        );

        return $trade->wasRecentlyCreated;
    }

    /**
     * Record that Alpaca did not report the order, so a human can investigate
     * instead of the platform guessing that nothing executed.
     */
    protected function markUnmatched(Order $order): void
    {
        $order->update([
            'reconciliation_status' => 'unmatched',
            'last_reconciled_at' => now(),
        ]);

        ProviderSyncLog::create([
            'provider' => 'alpaca',
            'operation' => 'reconcile-orders',
            'entity_type' => 'order',
            'entity_id' => $order->id,
            'status' => 'unmatched',
            'severity' => 'warning',
            'reference' => $order->provider_order_id
                ?: $order->provider_client_reference,
            'response' => ['symbol' => $order->symbol],
            'metadata' => [
                'reason' => 'Alpaca did not return a uniquely matching order.',
            ],
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Consume the reservation for the newly executed quantity and update the
     * local portfolio.
     *
     * The reservation is only ever reduced by the delta, so a repeated provider
     * payload can never release (or spend) funds twice.
     */
    protected function applyLocalFill(
        Order $order,
        float $quantity,
        float $price
    ): void {
        $value = $quantity * $price;

        if ($order->side === 'buy') {
            $portfolio = $this->lockPortfolio($order) ?? new Portfolio([
                'user_id' => $order->user_id,
                'symbol' => $order->symbol,
                'name' => $order->company ?? $order->symbol,
                'category' => 'foreign',
                'currency' => $order->currency ?? 'USD',
                'quantity' => 0,
                'cleared_quantity' => 0,
                'uncleared_quantity' => 0,
                'avg_price' => 0,
                'market_price' => $price,
            ]);

            $existingQuantity = (float) $portfolio->quantity;
            $existingTotal = $existingQuantity * (float) $portfolio->avg_price;

            $portfolio->quantity = $existingQuantity + $quantity;

            // Newly executed stock stays uncleared until settlement.
            $portfolio->uncleared_quantity =
                (float) $portfolio->uncleared_quantity + $quantity;

            $portfolio->avg_price = $portfolio->quantity > 0
                ? ($existingTotal + $value) / $portfolio->quantity
                : $price;

            $portfolio->market_price = $price;
            $portfolio->save();

            /**
             * The reserved cash has been spent on shares, so it leaves the
             * wallet entirely rather than returning to the cleared balance.
             */
            $this->lockWallet($order)?->consumeReservation($value);

            return;
        }

        $portfolio = $this->lockPortfolio($order);

        if ($portfolio) {
            $portfolio->quantity = max(
                0,
                (float) $portfolio->quantity - $quantity
            );
            $portfolio->uncleared_quantity = max(
                0,
                (float) $portfolio->uncleared_quantity - $quantity
            );
            $portfolio->market_price = $price;
            $portfolio->save();
        }

        $this->lockWallet($order)?->credit($value, 'uncleared');
    }

    /**
     * Hand back whatever did not execute.
     *
     * Mirrors CslOrderReconciliationService::releaseRemainingReservation so a
     * cancelled Global order refunds exactly the unexecuted remainder of the
     * original reservation and not a naira more.
     */
    protected function releaseRemainingReservation(
        Order $order,
        float $filled
    ): void {
        $remaining = max(0, (float) $order->quantity - $filled);

        if ($remaining <= 0) {
            return;
        }

        if ($order->side === 'buy') {
            $this->lockWallet($order)?->releaseReservation(
                $remaining * (float) $order->price
            );

            return;
        }

        $portfolio = $this->lockPortfolio($order);

        if (! $portfolio) {
            return;
        }

        $portfolio->decrement('uncleared_quantity', $remaining);
        $portfolio->increment('cleared_quantity', $remaining);
    }

    /**
     * Lock the user's portfolio row for this symbol, if they own any.
     */
    protected function lockPortfolio(Order $order): ?Portfolio
    {
        return Portfolio::query()
            ->where('user_id', $order->user_id)
            ->where('symbol', $order->symbol)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Lock the user's wallet in the order's currency, if one exists.
     */
    protected function lockWallet(Order $order): ?Wallet
    {
        return Wallet::query()
            ->where('user_id', $order->user_id)
            ->where('currency', $order->currency ?? 'USD')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Discover the provider order: id first, Xavier client reference second.
     */
    protected function matchProviderOrder(
        Order $order,
        array $providerOrders
    ): ?array {
        foreach ($providerOrders as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidateId = $this->stringValue($candidate['id'] ?? null);

            if ($order->provider_order_id
                && $candidateId === (string) $order->provider_order_id) {
                return $candidate;
            }
        }

        if ($order->provider_client_reference) {
            foreach ($providerOrders as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $clientOrderId = $this->stringValue(
                    $candidate['client_order_id'] ?? null
                );

                if ($clientOrderId
                    === (string) $order->provider_client_reference) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Normalise an Alpaca order status into Xavier's order vocabulary.
     */
    protected function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'filled' => 'filled',
            'partially_filled' => 'partially_filled',
            'canceled', 'cancelled', 'expired', 'replaced' => 'canceled',
            'rejected', 'rejected_for_day' => 'rejected',
            'failed' => 'failed',
            default => 'open',
        };
    }

    /**
     * Pull the order list out of whatever envelope Alpaca returned.
     */
    protected function extractOrders(array $response): array
    {
        foreach (['orders', 'data', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        // A bare list of order objects.
        if ($response !== [] && array_is_list($response)) {
            return $response;
        }

        return [];
    }

    protected function recordAudit(
        Order $order,
        string $status,
        bool $newTrade
    ): void {
        ActivityLog::log($order->user_id, match ($status) {
            'partially_filled' => 'stock_order_partially_filled',
            'filled' => 'stock_order_filled',
            'canceled' => 'stock_order_cancelled',
            default => 'provider_order_reconciled',
        }, [
            'order_id' => $order->id,
            'provider' => 'alpaca',
            'provider_order_id' => $order->provider_order_id,
            'provider_client_reference' => $order->provider_client_reference,
            'status' => $order->status,
            'filled_quantity' => (float) $order->filled_quantity,
            'new_trade' => $newTrade,
        ]);
    }

    protected function number(mixed $value): float
    {
        return $value === null || $value === '' || ! is_numeric($value)
            ? 0
            : (float) $value;
    }

    protected function numberString(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }

    protected function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
