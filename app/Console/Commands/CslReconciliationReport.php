<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CslReconciliationReport extends Command
{
    protected $signature = 'csl:reconciliation-report';

    protected $description = 'Report CSL orders that were matched, fallback matched, unmatched, or errored.';

    public function handle(): int
    {
        $query = Order::query()->where('provider', 'csl');

        $this->line('CSL RECONCILIATION REPORT');
        $this->line('============================');
        $this->line('Matched:              '.$query->clone()->where('reconciliation_status', 'matched')->count());
        $this->line('Fallback matched:     '.$query->clone()->where('reconciliation_status', 'matched_by_fallback')->count());
        $this->line('Pending:              '.$query->clone()->where('reconciliation_status', 'pending')->count());
        $this->line('Unmatched:             '.$query->clone()->where('reconciliation_status', 'unmatched')->count());
        $this->line('Unknown:                '.$query->clone()->where('reconciliation_status', 'unknown')->count());
        $this->line('Ambiguous:              '.$query->clone()->where('reconciliation_status', 'ambiguous')->count());
        $this->line('Unmatched:              '.$query->clone()->where('reconciliation_status', 'unmatched')->count());
        $this->line('Errors:                 '.$query->clone()->where('reconciliation_status', 'error')->count());
        $this->line('Open:                   '.$query->clone()->where('status', 'open')->count());
        $this->line('Partially filled:       '.$query->clone()->where('status', 'partially_filled')->count());
        $this->line('Cancellation requested: '.$query->clone()->where('provider_cancellation_status', 'requested')->count());

        $attention = $query->clone()
            ->where(function ($nested) {
                $nested->whereIn('reconciliation_status', [
                    'ambiguous', 'unmatched', 'unknown', 'error',
                ])->orWhere('provider_cancellation_status', 'unknown');
            })
            ->latest('last_reconciled_at')
            ->limit(20)
            ->get();

        if ($attention->isNotEmpty()) {
            $this->newLine();
            $this->line('Orders needing attention:');
            foreach ($attention as $order) {
                $this->line('');
                $this->line('CSL Account: '.($order->provider_market_account_id ?: 'N/A'));
                $this->line('Symbol: '.$order->symbol);
                $this->line('Side: '.strtoupper($order->side));
                $this->line('Quantity: '.$order->quantity);
                $this->line('Provider Order: '.($order->provider_order_id ?: 'N/A'));
            }
        }

        return self::SUCCESS;
    }
}
