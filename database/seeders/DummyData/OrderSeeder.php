<?php

namespace Database\Seeders\DummyData;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $markets = [
            [
                'name' => 'GLOBAL',
                'currency' => 'USD',
                'symbols' => ['AAPL', 'TSLA', 'MSFT', 'NVDA', 'AMZN'],
            ],
            [
                'name' => 'CRYPTO',
                'currency' => 'USD',
                'symbols' => ['BTC', 'ETH', 'SOL', 'BNB', 'XRP'],
            ],
            [
                'name' => 'NGX', // Local Stocks
                'currency' => 'NGN',
                'symbols' => ['ZENITHBANK', 'GTCO', 'DANGCEM', 'MTNN', 'AIRTELAFRI'],
            ],
            [
                'name' => 'FIXED INCOME',
                'currency' => 'NGN',
                'symbols' => ['FG132026S1', 'ABB2026S0', 'FGNSB_2027', 'CP_MTN_I', 'CP_DAN_X'],
            ],
        ];

        User::query()->orderBy('id')->get()->each(function ($user) use ($markets): void {
            foreach (range(1, 10) as $index) {
                $config = $markets[($user->id + $index) % count($markets)];
                $symbol = $config['symbols'][($user->id + $index) % count($config['symbols'])];
                $marketPrice = $config['name'] === 'NGX'
                    ? 10 + (($user->id * 13 + $index * 7) % 491)
                    : 50 + (($user->id * 137 + $index * 211) % 59951);
                $amount = 1000 + (($user->id * 997 + $index * 613) % 49001);
                $calculatedUnits = $amount / $marketPrice;
                $clientReference = sprintf('DEMO-ORDER-%d-%02d', $user->id, $index);

                Order::updateOrCreate(
                    ['provider_client_reference' => $clientReference],
                    [
                        'user_id' => $user->id,
                        'symbol' => $symbol,
                        'company' => $config['name'] === 'CRYPTO' ? null : 'Demo '.$symbol.' Holdings',
                        'side' => $index % 2 === 0 ? 'buy' : 'sell',
                        'type' => $index % 2 === 0 ? 'market' : 'limit',
                        'market_price' => $marketPrice,
                        'price' => $marketPrice,
                        'amount' => $amount,
                        'units' => $calculatedUnits,
                        'quantity' => $calculatedUnits,
                        'status' => ['open', 'partially_filled', 'filled', 'canceled'][$index % 4],
                        'market' => $config['name'],
                        'currency' => $config['currency'],
                        'source' => 'WEB_APP',
                    ]
                );
            }
        });
    }
}
