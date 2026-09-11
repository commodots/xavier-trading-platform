<?php

namespace App\Services\CSL;

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Trade;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class CslOrderReconciliationService
{
    public function __construct(
        protected CslTradeXClient $xt
    ) {}

    public function reconcile(
        string $marketAccountId
    ): array {

        $open = $this->xt->openOrders(
            $marketAccountId
        );

        $executed = $this->xt->executedOrders(
            $marketAccountId
        );

        $cancelled = $this->xt->cancelledOrdersByDate(
            $marketAccountId,
            now()->subDays(7)->toDateString(),
            now()->toDateString()
        );

        $openRows = $this->extractRows(
            $open
        );

        $executedRows = $this->extractRows(
            $executed
        );

        $cancelledRows = $this->extractRows(
            $cancelled
        );

        $updated = 0;
        $trades = 0;

        foreach ($openRows as $row) {

            $order = $this->findOrder($row);

            if (! $order) {
                continue;
            }

            $order->update([
                'status' => $this->mapOrderStatus($row),
                'filled_quantity' => $row['filled_quantity'] ?? 0,
                'provider_response' => $row,
            ]);

            $updated++;
        }

        foreach ($executedRows as $row) {

            $order = $this->findOrder($row);

            if (! $order) {
                continue;
            }

            DB::transaction(function () use (
                $order,
                $row,
                &$trades
            ) {

                $filled =
                  $row['filled_quantity']
                  ?? $row['order_quantity']
                  ?? 0;

                $previouslyFilled = (float) ($order->filled_quantity ?? 0);
                $newlyFilled = max(0, (float) $filled - $previouslyFilled);

                $orderStatus = $filled < (float) ($row['order_quantity'] ?? $filled)
                  ? 'partially_filled'
                  : 'filled';

                $order->update([
                    'status' => $orderStatus,
                    'filled_quantity' => $filled,
                    'provider_response' => $row,
                ]);

                Trade::updateOrCreate(
                    [
                        'provider' => 'csl',
                        'provider_trade_id' => $row['trade_number']
                            ?? $row['trade_id']
                            ?? $row['order_id']
                            ?? $order->provider_order_id,
                    ],
                    [
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,

                        'provider_trade_id' => $row['trade_number']
                          ?? $row['order_id']
                          ?? null,

                        'provider_order_id' => $row['order_id']
                          ?? $order->provider_order_id,

                        'price' => $row['average_fill_price']
                          ?? $row['limit_price']
                          ?? $order->price,

                        'quantity' => $filled,

                        'currency' => $order->currency,

                        'reference' => $row['order_id']
                          ?? null,

                        'settlement_status' => 'pending',

                        'provider_response' => $row,

                        'provider_executed_at' => $row['time_placed']
                          ?? null,
                    ]
                );

                if ($newlyFilled > 0) {
                    $this->applyLocalFill($order, $newlyFilled, (float) ($row['average_fill_price'] ?? $order->price));
                }

                $trades++;
            });
        }

        foreach ($cancelledRows as $row) {
            $order = $this->findOrder($row);

            if (! $order) {
                continue;
            }

            $order->update([
                'status' => 'canceled',
                'provider_response' => $row,
            ]);

            $updated++;
        }

        return [
            'open_orders' => count($openRows),
            'executed_orders' => count($executedRows),
            'cancelled_orders' => count($cancelledRows),
            'orders_updated' => $updated,
            'trades_processed' => $trades,
        ];
    }

    protected function applyLocalFill(Order $order, float $quantity, float $price): void
    {
        $value = $quantity * $price;

        if ($order->side === 'buy') {
            Portfolio::query()->updateOrCreate(
                [
                    'user_id' => $order->user_id,
                    'symbol' => $order->symbol,
                ],
                [
                    'name' => $order->company ?? $order->symbol,
                    'category' => 'local',
                    'currency' => $order->currency,
                    'market_price' => $price,
                ]
            );

            $portfolio = Portfolio::query()
                ->where('user_id', $order->user_id)
                ->where('symbol', $order->symbol)
                ->lockForUpdate()
                ->firstOrFail();
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

            return;
        }

        $wallet = Wallet::query()
            ->where('user_id', $order->user_id)
            ->where('currency', $order->currency)
            ->lockForUpdate()
            ->firstOrFail();
        $wallet->credit($value, 'uncleared');
    }

    protected function findOrder(
        array $row
    ): ?Order {

        $providerOrderId =
          $row['order_id']
          ?? $row['order_identifier']
          ?? null;

        if (! $providerOrderId) {
            return null;
        }

        return Order::where(
            'provider',
            'csl'
        )->where(
            'provider_order_id',
            $providerOrderId
        )->first();
    }

    protected function mapOrderStatus(
        array $row
    ): string {

        $status = strtolower(
            (string) (
                $row['order_status'] ?? ''
            )
        );

        if (
            str_contains($status, 'fill')
            && ! str_contains($status, 'partial')
        ) {
            return 'filled';
        }

        if (
            str_contains($status, 'partial')
        ) {
            return 'partially_filled';
        }

        if (
            str_contains($status, 'cancel')
        ) {
            return 'canceled';
        }

        return 'open';
    }

    protected function extractRows(
        array $response
    ): array {

        foreach (
            [
                'result',
                'data',
                'orders',
                'GetCRXTMarketOpenOrders',
                'GetCRXTExecutedOrders',
            ] as $key
        ) {

            if (
                isset($response[$key])
                && is_array($response[$key])
            ) {
                return $response[$key];
            }
        }

        return [];
    }
}
