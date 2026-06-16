<?php

namespace App\Jobs;

use App\Models\Trade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SettleTradeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Trade $trade)
    {
        $this->onQueue('settlement');
    }

    public function handle(): void
    {
        try {
            $this->trade->update([
                'settlement_status' => 'settled',
                'is_settled' => true,
                'settlement_date' => now(),
            ]);

            Log::info("Trade #{$this->trade->id} settled successfully.");
        } catch (\Exception $e) {
            $this->trade->update([
                'settlement_status' => 'failed',
            ]);

            Log::error("Trade #{$this->trade->id} settlement failed: {$e->getMessage()}");
        }
    }
}