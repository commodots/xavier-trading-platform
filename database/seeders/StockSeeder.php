<?php

namespace Database\Seeders;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $stocks = ['AAPL', 'TSLA', 'MSFT', 'NFLX', 'NVDA'];

        User::all()->each(function ($user) use ($stocks) {
            foreach (range(1, 5) as $i) {
                Trade::create([
                    'user_id' => $user->id,
                    'pair' => fake()->randomElement($stocks),
                    'type' => fake()->randomElement(['buy', 'sell']),
                    'amount' => fake()->randomFloat(2, 1, 20),
                    'entry_price' => fake()->randomFloat(2, 50, 500),
                    'status' => 'completed',
                ]);
            }
        });
    }
}
