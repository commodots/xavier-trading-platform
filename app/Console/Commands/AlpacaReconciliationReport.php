<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class AlpacaReconciliationReport extends Command
{
    protected $signature = 'alpaca:reconciliation-report';

    protected $description = 'Report Global (Alpaca) order reconciliation state';

    public function handle(): int
    {
        $query = Order::query()->where('provider', 'alpaca');

        $this->line('ALPACA RECONCILIATION REPORT');
        $this->line('============================');
        $this->line('Total:                 '.$query->clone()->count());
        $this->line('Pending:               '.$query->clone()->where('reconciliation_status', 'pending')->count());
        $this->line('Matched:               '.$query->clone()->where('reconciliation_status', 'matched')->count());
        $this->line('Unknown:               '.$query->clone()->where('reconciliation_status', 'unknown')->count());
        $this->line('Unmatched:             '.$query->clone()->where('reconciliation_status', 'unmatched')->count());
        $this->line('Open:                  '.$query->clone()->where('status', 'open')->count());
        $this->line('Partially filled:      '.$query->clone()->where('status', 'partially_filled')->count());
        $this->line('Cancel requested:      '.$query->clone()->where('provider_cancellation_status', 'requested')->count());
        $this->line('Cancel unknown:        '.$query->clone()->where('provider_cancellation_status', 'unknown')->count());
        $this->line('Cancel confirmed:      '.$query->clone()->where('provider_cancellation_status', 'confirmed')->count());

        $needsAttention = $query->clone()
            ->whereIn('reconciliation_status', ['unknown', 'unmatched', 'error'])
            ->latest('last_reconciled_at')
            ->limit(20)
            ->get();

        if ($needsAttention->isNotEmpty()) {
            $this->newLine();
            $this->line('Orders needing attention:');

            foreach ($needsAttention as $order) {
                $this->line('');
                $this->line('Xavier Order:  '.$order->id);
                $this->line('Provider Order: '.($order->provider_order_id ?: 'N/A'));
                $this->line('Client Ref:    '.($order->provider_client_reference ?: 'N/A'));
                $this->line('Symbol:        '.$order->symbol);
                $this->line('Side:          '.strtoupper($order->side));
                $this->line('Quantity:      '.$order->quantity);
                $this->line('Filled:        '.$order->filled_quantity);
                $this->line('Status:        '.$order->status);
                $this->line('Reconcile:     '.$order->reconciliation_status);
            }
        }

        return self::SUCCESS;
    }
}
