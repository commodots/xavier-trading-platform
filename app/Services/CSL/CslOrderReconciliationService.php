<?php

namespace App\Services\CSL;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderSyncLog;
use App\Models\Trade;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class CslOrderReconciliationService
{
    public function __construct(
        protected CslTradeXClient $xt
    ) {}

    public function reconcile(string $marketAccountId): array
    {
        $openRows = $this->extractRows($this->xt->openOrders($marketAccountId));
        $executedRows = $this->extractRows($this->xt->executedOrders($marketAccountId));
        $canceledRows = $this->extractRows($this->xt->cancelledOrdersByDate(
            $marketAccountId,
            now()->subDays(7)->toDateString(),
            now()->toDateString()
        ));

        $updated = 0;
        $trades = 0;
        $fallbackMatches = 0;

        foreach ($openRows as $row) {
            [$order, $matchedByFallback] = $this->resolveOrder($row, $marketAccountId);

            if (! $order) {
                $this->markUnmatched($row);

                continue;
            }

            $this->updateOrderFromProvider($order, $row, $matchedByFallback);
            $updated++;
            $fallbackMatches += (int) $matchedByFallback;
        }

        foreach ($executedRows as $row) {
            [$order, $matchedByFallback] = $this->resolveOrder($row, $marketAccountId);

            if (! $order) {
                $this->markUnmatched($row);

                continue;
            }

            $created = DB::transaction(function () use ($order, $row, $matchedByFallback): bool {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $filled = $this->number($row['filled_quantity'] ?? $row['order_quantity'] ?? 0);
                $previouslyFilled = (float) $order->filled_quantity;
                $newlyFilled = max(0, $filled - $previouslyFilled);
                $providerOrderId = $this->stringValue(
                    $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
                );
                $fillPrice = $this->number(
                    $row['average_fill_price'] ?? $row['fill_price'] ?? $row['limit_price'] ?? $order->price
                );

                $updates = [
                    'status' => $this->mapOrderStatus($row),
                    'filled_quantity' => $filled,
                    'provider_response' => $row,
                    'last_reconciled_at' => now(),
                    'reconciliation_status' => $matchedByFallback ? 'matched_by_fallback' : 'matched',
                ];

                if ($providerOrderId) {
                    $updates['provider_order_id'] = $providerOrderId;
                }

                $order->update($updates);

                if ($newlyFilled <= 0) {
                    return false;
                }

                $tradeReference = 'CSL-FILL-'.$order->id.'-'.$this->numberString($filled);
                $trade = Trade::firstOrCreate(
                    [
                        'provider' => 'csl',
                        'reference' => $tradeReference,
                    ],
                    [
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'provider_order_id' => $providerOrderId ?: $order->provider_order_id,
                        'provider_trade_id' => $row['trade_number'] ?? $row['trade_id'] ?? null,
                        'price' => $fillPrice,
                        'quantity' => $newlyFilled,
                        'amount' => $newlyFilled * $fillPrice,
                        'currency' => $order->currency,
                        'settlement_status' => 'pending',
                        'status' => 'pending',
                        'is_settled' => false,
                        'provider_response' => $row,
                        'provider_executed_at' => $row['time_placed'] ?? null,
                    ]
                );

                if ($trade->wasRecentlyCreated) {
                    $this->applyLocalFill($order, $newlyFilled, $fillPrice);

                    return true;
                }

                return false;
            });

            $trades += (int) $created;
            $updated++;
            $fallbackMatches += (int) $matchedByFallback;
        }

        foreach ($canceledRows as $row) {
            [$order, $matchedByFallback] = $this->resolveOrder($row, $marketAccountId);

            if (! $order) {
                $this->markUnmatched($row);

                continue;
            }

            DB::transaction(function () use ($order, $row, $matchedByFallback): void {
                $filled = $this->number($row['filled_quantity'] ?? $row['executed_quantity'] ?? $order->filled_quantity);
                $previouslyFilled = (float) $order->filled_quantity;
                $newlyFilled = max(0, $filled - $previouslyFilled);

                if ($newlyFilled > 0) {
                    $this->applyLocalFill($order, $newlyFilled, $this->number($row['average_fill_price'] ?? $order->price));
                }

                $order->update([
                    'status' => 'canceled',
                    'filled_quantity' => $filled,
                    'provider_response' => $row,
                    'last_reconciled_at' => now(),
                    'reconciliation_status' => $matchedByFallback ? 'matched_by_fallback' : 'matched',
                    'provider_cancellation_status' => 'confirmed',
                ]);

                $this->releaseRemainingReservation($order, $filled);
            });

            $updated++;
            $fallbackMatches += (int) $matchedByFallback;
        }

        return [
            'open_orders' => count($openRows),
            'executed_orders' => count($executedRows),
            'canceled_orders' => count($canceledRows),
            'orders_updated' => $updated,
            'trades_processed' => $trades,
            'fallback_matches' => $fallbackMatches,
        ];
    }

    protected function resolveOrder(array $row, string $marketAccountId): array
    {
        $providerOrderId = $this->stringValue(
            $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
        );

        if ($providerOrderId) {
            $order = Order::query()
                ->where('provider', 'csl')
                ->where('provider_order_id', $providerOrderId)
                ->first();

            if ($order) {
                return [$order, false];
            }
        }

        $symbol = strtoupper((string) ($row['symbol_code'] ?? $row['SYMBOL_CODE'] ?? ''));
        $side = strtolower((string) ($row['order_side'] ?? $row['ORDER_SIDE'] ?? ''));
        $quantity = $this->number($row['order_quantity'] ?? $row['ORDER_QUANTITY'] ?? 0);

        if ($symbol === '' || ! in_array($side, ['buy', 'sell'], true) || $quantity <= 0) {
            return [null, false];
        }

        $order = Order::query()
            ->where('provider', 'csl')
            ->where('provider_market_account_id', $marketAccountId)
            ->where('symbol', $symbol)
            ->where('side', $side)
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('provider_submitted_at', '>=', now()->subDays(2))
            ->orderBy('provider_submitted_at')
            ->get()
            ->first(fn (Order $candidate): bool => abs((float) $candidate->quantity - $quantity) < 0.000001);

        return [$order, $order !== null];
    }

    protected function updateOrderFromProvider(Order $order, array $row, bool $matchedByFallback): void
    {
        $providerOrderId = $this->stringValue(
            $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
        );

        $updates = [
            'status' => $this->mapOrderStatus($row),
            'filled_quantity' => $this->number($row['filled_quantity'] ?? 0),
            'provider_response' => $row,
            'last_reconciled_at' => now(),
            'reconciliation_status' => $matchedByFallback ? 'matched_by_fallback' : 'matched',
        ];

        if ($providerOrderId) {
            $updates['provider_order_id'] = $providerOrderId;
        }

        $order->update($updates);
    }

    protected function processExecution(Order $order, array $row, bool $matchedByFallback): bool
    {
        return DB::transaction(function () use ($order, $row, $matchedByFallback): bool {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $filled = $this->number($row['filled_quantity'] ?? $row['executed_quantity'] ?? $row['order_quantity'] ?? 0);
            $newlyFilled = max(0, $filled - (float) $order->filled_quantity);
            $providerOrderId = $this->stringValue(
                $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
            );
            $fillPrice = $this->number(
                $row['average_fill_price'] ?? $row['fill_price'] ?? $row['limit_price'] ?? $order->price
            );

            $updates = [
                'status' => $this->mapOrderStatus($row),
                'filled_quantity' => max($filled, (float) $order->filled_quantity),
                'provider_response' => $row,
                'last_reconciled_at' => now(),
                'reconciliation_status' => $matchedByFallback ? 'matched_by_fallback' : 'matched',
            ];

            if ($providerOrderId) {
                $updates['provider_order_id'] = $providerOrderId;
            }

            $order->update($updates);

            if ($newlyFilled <= 0) {
                return false;
            }

            $tradeReference = 'CSL-FILL-'.$order->id.'-'.$this->numberString($filled);
            $trade = Trade::firstOrCreate(
                [
                    'provider' => 'csl',
                    'reference' => $tradeReference,
                ],
                [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'provider_order_id' => $providerOrderId ?: $order->provider_order_id,
                    'provider_trade_id' => $row['trade_number'] ?? $row['trade_id'] ?? null,
                    'price' => $fillPrice,
                    'quantity' => $newlyFilled,
                    'amount' => $newlyFilled * $fillPrice,
                    'currency' => $order->currency,
                    'settlement_status' => 'pending',
                    'status' => 'pending',
                    'is_settled' => false,
                    'provider_response' => $row,
                    'provider_executed_at' => $row['time_placed'] ?? null,
                ]
            );

            if (! $trade->wasRecentlyCreated) {
                return false;
            }

            $this->applyLocalFill($order, $newlyFilled, $fillPrice);

            return true;
        });
    }

    protected function applyLocalFill(Order $order, float $quantity, float $price): void
    {
        $value = $quantity * $price;

        if ($order->side === 'buy') {
            $portfolio = Portfolio::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $order->user_id, 'symbol' => $order->symbol],
                [
                    'name' => $order->company ?? $order->symbol,
                    'category' => 'local',
                    'currency' => $order->currency,
                    'quantity' => 0,
                    'cleared_quantity' => 0,
                    'uncleared_quantity' => 0,
                    'avg_price' => 0,
                    'market_price' => $price,
                ]
            );

            $oldQuantity = (float) $portfolio->quantity;
            $newQuantity = $oldQuantity + $quantity;
            $portfolio->update([
                'quantity' => $newQuantity,
                'uncleared_quantity' => (float) $portfolio->uncleared_quantity + $quantity,
                'avg_price' => $newQuantity > 0
                  ? (($oldQuantity * (float) $portfolio->avg_price) + $value) / $newQuantity
                  : $price,
                'market_price' => $price,
            ]);

            Wallet::query()
                ->where('user_id', $order->user_id)
                ->where('currency', $order->currency)
                ->lockForUpdate()
                ->firstOrFail()
                ->decrement('locked', $value);

            return;
        }

        $portfolio = Portfolio::query()
            ->where('user_id', $order->user_id)
            ->where('symbol', $order->symbol)
            ->lockForUpdate()
            ->firstOrFail();
        $portfolio->decrement('quantity', $quantity);
        $portfolio->decrement('uncleared_quantity', $quantity);
    }

    protected function releaseRemainingReservation(Order $order, float $filled): void
    {
        $remaining = max(0, (float) $order->quantity - $filled);

        if ($remaining <= 0) {
            return;
        }

        if ($order->side === 'buy') {
            Wallet::query()
                ->where('user_id', $order->user_id)
                ->where('currency', $order->currency)
                ->lockForUpdate()
                ->firstOrFail()
                ->releaseReservation($remaining * (float) $order->price);

            return;
        }

        $portfolio = Portfolio::query()
            ->where('user_id', $order->user_id)
            ->where('symbol', $order->symbol)
            ->lockForUpdate()
            ->firstOrFail();
        $portfolio->decrement('uncleared_quantity', $remaining);
        $portfolio->increment('cleared_quantity', $remaining);
    }

    protected function markUnmatched(array $row): void
    {
        $providerOrderId = $this->stringValue(
            $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
        );

        $symbol = strtoupper((string) ($row['symbol_code'] ?? $row['SYMBOL_CODE'] ?? ''));
        $side = strtolower((string) ($row['order_side'] ?? $row['ORDER_SIDE'] ?? ''));
        $quantity = $this->number($row['order_quantity'] ?? $row['ORDER_QUANTITY'] ?? 0);

        $query = Order::query()->where('provider', 'csl');

        if ($providerOrderId) {
            $query->where('provider_order_id', $providerOrderId);
        } elseif ($symbol !== '' && $side !== '' && $quantity > 0) {
            $query->where('symbol', $symbol)
                ->where('side', $side)
                ->where('provider_market_account_id', $row['market_account_id'] ?? null)
                ->whereIn('status', ['open', 'partially_filled'])
                ->where('provider_submitted_at', '>=', now()->subDays(2));
        }

        $query->update([
            'reconciliation_status' => 'unmatched',
            'last_reconciled_at' => now(),
        ]);

        ProviderSyncLog::create([
            'provider' => 'csl',
            'operation' => 'reconcile-orders',
            'status' => 'unmatched',
            'reference' => $providerOrderId ?: ($symbol ?: 'unknown'),
            'response' => $row,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    protected function mapOrderStatus(array $row): string
    {
        $status = strtolower((string) ($row['order_status'] ?? $row['status'] ?? ''));

        if (str_contains($status, 'partial')) {
            return 'partially_filled';
        }

        if (str_contains($status, 'fill') || str_contains($status, 'execut')) {
            return 'filled';
        }

        if (str_contains($status, 'cancel')) {
            return 'canceled';
        }

        return 'open';
    }

    protected function number(mixed $value): float
    {
        return $value === null || $value === '' || ! is_numeric($value) ? 0 : (float) $value;
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

    protected function extractRows(array $response): array
    {
        foreach (['result', 'data', 'orders', 'GetCRXTMarketOpenOrders', 'GetCRXTExecutedOrders', 'GetCRXTCancelledOrderByDate'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }
}
