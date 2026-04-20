<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Watchlist;
use App\Models\WatchlistPriceHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWatchlistPrices extends Command
{
    protected $signature = 'watchlist:sync-prices';
    protected $description = 'Sync current prices for all watchlist items from external APIs';

    public function handle(): void
    {
        $items = Watchlist::all();
        $this->info("Syncing prices for " . $items->count() . " items...");

        foreach ($items as $item) {
            try {
                $price = $this->getPriceFromApi($item);
                
                if ($price) {
                    // Update the current price on the item
                    $item->update(['price' => $price]);

                    // Record history entry for trend tracking
                    WatchlistPriceHistory::create([
                        'watchlist_id' => $item->id,
                        'price' => $price,
                        'recorded_at' => now(),
                    ]);

                    $this->line("Updated {$item->symbol}: {$price}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync price for {$item->symbol}: " . $e->getMessage());
            }
        }

        $this->info('Watchlist price synchronization complete.');
    }

    /**
     * Fetch current price based on market type
     */
    private function getPriceFromApi($item): ?float
    {
        switch (strtoupper($item->market)) {
            case 'CRYPTO':
                // Using CoinGecko for crypto prices
                $response = Http::get("https://api.coingecko.com/api/v3/simple/price", [
                    'ids' => strtolower($item->name), 
                    'vs_currencies' => 'usd',
                ]);
                return $response->json()[strtolower($item->name)]['usd'] ?? null;

            case 'NGX':
                // Using the internal/dummy NGX market endpoint
                $response = Http::get(config('app.url') . "/api/dummy/ngx/market/{$item->symbol}");
                return $response->json()['bid'] ?? $response->json()['price'] ?? null;

            case 'GLOBAL':
            case 'INTERNATIONAL':
                // Placeholder for international stocks API (e.g., Finnhub or Alpha Vantage)
                // $response = Http::get("https://api.example.com/quote", ['symbol' => $item->symbol]);
                // return $response->json()['c'] ?? null;
                return null;

            default:
                return null;
        }
    }
}
