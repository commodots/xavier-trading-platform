<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\StockTrade;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $stocks = ['AAPL', 'TSLA', 'MSFT', 'NFLX', 'NVDA'];

        User::all()->each(function ($user) use ($stocks) {
            foreach (range(1, 5) as $i) {
                StockTrade::create([
                    'user_id' => $user->id,
                    'symbol' => fake()->randomElement($stocks),
                    'units' => fake()->randomFloat(2, 1, 20),
                    'price' => fake()->randomFloat(2, 50, 500),
                    'type' => fake()->randomElement(['buy', 'sell']),
                ]);
            }
        });
    }
}
