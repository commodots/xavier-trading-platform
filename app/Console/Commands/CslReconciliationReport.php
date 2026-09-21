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
        $this->line('Unknown:              '.$query->clone()->where('reconciliation_status', 'unknown')->count());
        $this->line('Errors:                '.$query->clone()->where('reconciliation_status', 'error')->count());

        $unmatched = $query->clone()
            ->whereIn('reconciliation_status', ['unmatched', 'unknown', 'error'])
            ->latest('last_reconciled_at')
            ->limit(20)
            ->get();

        if ($unmatched->isNotEmpty()) {
            $this->newLine();
            $this->line('Unmatched orders:');
            foreach ($unmatched as $order) {
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
