<?php

namespace App\Services\CSL;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\ProviderSyncLog;
use App\Models\Trade;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                $filled = $this->number(
                    $row['filled_quantity']
                    ?? $row['FILLED_QUANTITY']
                    ?? $row['executed_quantity']
                    ?? $row['order_quantity']
                    ?? 0
                );
                $previouslyFilled = (float) $order->filled_quantity;
                $newlyFilled = max(0, $filled - $previouslyFilled);
                $providerOrderId = $this->stringValue(
                    $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
                );
                $fillPrice = $this->number(
                    $row['average_fill_price']
                    ?? $row['AVERAGE_FILL_PRICE']
                    ?? $row['fill_price']
                    ?? $row['LIMIT_PRICE']
                    ?? $row['limit_price']
                    ?? $order->price
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
                        'provider_executed_at' => $row['time_placed'] ?? $row['TIME_PLACED'] ?? null,
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
                // Cancelled-order rows use UPPER_CASE keys and a misspelled
                // FILLED_QUAMTITY field; executed rows use lowercase keys.
                $filled = $this->number(
                    $row['filled_quantity']
                    ?? $row['FILLED_QUANTITY']
                    ?? $row['FILLED_QUAMTITY']
                    ?? $row['executed_quantity']
                    ?? $order->filled_quantity
                );
                $previouslyFilled = (float) $order->filled_quantity;
                $newlyFilled = max(0, $filled - $previouslyFilled);

                if ($newlyFilled > 0) {
                    $this->applyLocalFill(
                        $order,
                        $newlyFilled,
                        $this->number(
                            $row['average_fill_price']
                            ?? $row['AVERAGE_FILL_PRICE']
                            ?? $order->price
                        )
                    );
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

        $matches = Order::query()
            ->where('provider', 'csl')
            ->where('provider_market_account_id', $marketAccountId)
            ->where('symbol', $symbol)
            ->where('side', $side)
            ->whereIn('status', ['open', 'partially_filled'])
            ->where('provider_submitted_at', '>=', now()->subDays(2))
            ->orderBy('provider_submitted_at')
            ->get()
            ->filter(fn (Order $candidate): bool => abs((float) $candidate->quantity - $quantity) < 0.000001);

        /*
         * Correlation must be unambiguous. If two local orders look identical
         * we cannot tell which one CSL is reporting, and guessing would mark
         * the wrong order as executed. Leave them for investigation instead.
         */
        if ($matches->count() !== 1) {
            if ($matches->isNotEmpty()) {
                Log::warning('CSL order correlation was ambiguous; orders left unmatched for investigation', [
                    'market_account_id' => $marketAccountId,
                    'symbol' => $symbol,
                    'side' => $side,
                    'quantity' => $quantity,
                    'candidate_order_ids' => $matches->pluck('id')->all(),
                ]);
            }

            return [null, false];
        }

        $order = $matches->first();

        Log::warning('CSL order matched using fallback correlation', [
            'order_id' => $order->id,
            'provider_order_id' => $providerOrderId,
            'symbol' => $order->symbol,
            'side' => $order->side,
        ]);

        return [$order, true];
    }

    protected function updateOrderFromProvider(Order $order, array $row, bool $matchedByFallback): void
    {
        $providerOrderId = $this->stringValue(
            $row['order_id'] ?? $row['ORDER_ID'] ?? $row['order_identifier'] ?? null
        );

        $updates = [
            'status' => $this->mapOrderStatus($row),

            /*
             * Open-order payloads may omit (or zero) the filled quantity.
             * Never reduce a fill we already recorded.
             */
            'filled_quantity' => max(
                $this->number($row['filled_quantity'] ?? 0),
                (float) $order->filled_quantity
            ),

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
        foreach (['result', 'data', 'orders'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        foreach (['GetCRXTMarketOpenOrders', 'GetCRXTExecutedOrders', 'GetCRXTCancelledOrderByDate'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        if (isset($response['result'][0]) && is_array($response['result'][0])) {
            foreach (['GetCRXTMarketOpenOrders', 'GetCRXTExecutedOrders', 'GetCRXTCancelledOrderByDate'] as $key) {
                if (isset($response['result'][0][$key]) && is_array($response['result'][0][$key])) {
                    return $response['result'][0][$key];
                }
            }
        }

        return [];
    }
}
