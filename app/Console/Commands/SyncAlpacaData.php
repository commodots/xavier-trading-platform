<?php

namespace App\Console\Commands;

use App\Models\AlpacaProvider;
use App\Models\Order;
use App\Models\Position;
use Illuminate\Console\Command;

class SyncAlpacaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-alpaca-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alpaca = new AlpacaProvider;

        $positions = $alpaca->getPositions();

        foreach ($positions as $p) {
            // Find the last user who traded this symbol to attribute the position
            // Or map this to a specific master 'house' user ID.
            $lastOrder = Order::where('symbol', $p['symbol'])->latest()->first();
            $userId = $lastOrder ? $lastOrder->user_id : 1;

            Position::updateOrCreate(
                ['symbol' => $p['symbol'], 'user_id' => $userId],
                [
                    'qty' => $p['qty'],
                    'avg_price' => $p['avg_entry_price'],
                ]
            );
        }
    }
}
