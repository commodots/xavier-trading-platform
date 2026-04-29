<?php

namespace App\Console\Commands;

use App\Models\Symbol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncSymbols extends Command
{
    protected $signature = 'stocks:sync';

    protected $description = 'Sync global symbols from Finnhub to local DB';

    public function handle()
    {
        $this->info('Fetching symbols from Finnhub...');

        $response = Http::get('https://finnhub.io/api/v1/stock/symbol', [
            'exchange' => 'US',
            'token' => config('services.finnhub.api_key'),
        ]);

        if ($response->failed()) {
            $this->error('Failed to fetch data.');

            return;
        }

        $symbols = $response->json();
        $this->info('Processing '.count($symbols).' symbols...');

        foreach ($symbols as $s) {
            Symbol::updateOrCreate(
                ['symbol' => $s['symbol']],
                [
                    'name' => $s['description'] ?? null,
                    'type' => $s['type'] ?? null,
                    'exchange' => $s['exchange'] ?? null,
                ]
            );
        }

        $this->info('Sync complete!');
    }
}
