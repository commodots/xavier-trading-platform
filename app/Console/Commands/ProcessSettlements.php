<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\SettlementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessSettlements extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'settlements:process';

    /**
     * The console command description.
     */
    protected $description = 'Finalize T+2 settlements by moving uncleared assets and cash to cleared status.';

    /**
     * Execute the console command.
     */
        public function handle(SettlementService $settlementService)
    {
        $today = now()->toDateString();
        $this->info("Starting settlement process for {$today}...");

         // Get trades that are due for settlement today or were missed in previous days
        $orders = Order::whereHas('trades', function ($query) use ($today) {
            $query->where('settlement_status', 'pending')
                  ->whereDate('settlement_date', '<=', $today);
        })->get();

        if ($orders->isEmpty()) {
            $this->info("No pending settlements found for {$today}.");
            return 0;
        }

        $this->info("Processing settlements for {$orders->count()} orders...");

        foreach ($orders as $order) {
            try {
                // Let the service do all the heavy lifting!
                $settlementService->settleOrder($order);
                $this->info("Successfully settled Order ID: {$order->id}");
            } catch (\Exception $e) {
                $this->error("Failed to settle Order ID {$order->id}: " . $e->getMessage());
                Log::error("Settlement Error [Order: {$order->id}]: " . $e->getMessage());
            }
        }

        $this->info('Settlement process completed!');
        return 0;
    }
}