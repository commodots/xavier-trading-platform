<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Order;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $markets = [
            [
                'name' => 'GLOBAL',
                'currency' => 'USD',
                'symbols' => ['AAPL', 'TSLA', 'MSFT', 'NVDA', 'AMZN']
            ],
            [
                'name' => 'CRYPTO',
                'currency' => 'USD',
                'symbols' => ['BTC', 'ETH', 'SOL', 'BNB', 'XRP']
            ],
            [
                'name' => 'NGX', // Local Stocks
                'currency' => 'NGN',
                'symbols' => ['ZENITHBANK', 'GTCO', 'DANGCEM', 'MTNN', 'AIRTELAFRI']
            ],
            [
                'name' => 'FIXED INCOME', 
                'currency' => 'NGN',
                'symbols' => ['FG132026S1', 'ABB2026S0', 'FGNSB_2027', 'CP_MTN_I','CP_DAN_X']
            ]
        ];

        User::all()->each(function ($user) use ($markets) {
            foreach (range(1, 10) as $i) {

                $config = fake()->randomElement($markets);

                $minPrice = $config['name'] === 'NGX' ? 10 : 50;
                $maxPrice = $config['name'] === 'NGX' ? 500 : 60000;

                $marketPrice = fake()->randomFloat(2, $minPrice, $maxPrice);
                $amount = fake()->randomFloat(2, 1000, 50000);
                $calculatedUnits = $amount / $marketPrice;

                Order::create([
                    'user_id' => $user->id,
                    'symbol' => fake()->randomElement($config['symbols']),
                    'company' => $config['name'] === 'CRYPTO' ? null : fake()->company(),
                    'side' => fake()->randomElement(['buy', 'sell']),
                    'type' => fake()->randomElement(['market', 'limit']),
                    'market_price' => $marketPrice,
                    'price' => $marketPrice,
                    'amount' => $amount,
                    'units' => $calculatedUnits,
                    'quantity' => $calculatedUnits,
                    'status' => fake()->randomElement(['open', 'partially_filled', 'filled', 'canceled']),
                    'market' => $config['name'],
                    'currency' => $config['currency'],
                    'source' => 'WEB_APP',
                ]);
            }
        });
    }
}
