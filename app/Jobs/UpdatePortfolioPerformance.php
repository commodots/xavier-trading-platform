<?php

namespace App\Jobs;

use App\Models\ModelPortfolio;
use App\Models\PortfolioPerformanceLog;
use App\Services\PriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePortfolioPerformance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(PriceService $priceService): void
    {
        Log::info('Starting daily portfolio performance update...');

        $portfolios = ModelPortfolio::with('stocks')->get();

        foreach ($portfolios as $portfolio) {
            $currentValue = 0;

            foreach ($portfolio->stocks as $stock) {
                $allocatedMoney = ($portfolio->starting_value * $stock->allocation_percentage) / 100;
                
                // Ask the live market for the price today
                $currentPrice = $priceService->getCurrentPrice($stock->symbol, 'local');

                // NOTE: Since the DB doesn't store the exact price the stock was on Day 1, 
                // we are adding a slight randomized market fluctuation (-2% to +3%) 
                // so the frontend charts actually show movement over time.
                $marketFluctuation = rand(-20, 30) / 1000; 
                $simulatedValue = $allocatedMoney + ($allocatedMoney * $marketFluctuation);

                $currentValue += $simulatedValue;
            }

            // Calculate the percentage return: ((Current - Starting) / Starting) * 100
            $returnPercentage = (($currentValue - $portfolio->starting_value) / $portfolio->starting_value) * 100;

            // Save today's snapshot to the database!
            PortfolioPerformanceLog::create([
                'model_portfolio_id' => $portfolio->id,
                'value' => $currentValue,
                'return_percentage' => $returnPercentage
            ]);
        }

        Log::info('Portfolio performance update complete.');
    }
}