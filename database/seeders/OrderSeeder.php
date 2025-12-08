<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Order;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $symbols = ['AAPL', 'TSLA', 'MSFT', 'NVDA'];

        User::all()->each(function ($user) use ($symbols) {
            foreach (range(1, 5) as $i) {
                Order::create([
                    'user_id' => $user->id,
                    'symbol' => fake()->randomElement($symbols),
                    'company' => fake()->company(),
                    'market_price' => fake()->randomFloat(2, 50, 500),
                    'amount' => fake()->randomFloat(2, 500, 5000),
                    'units' => fake()->randomFloat(4, 1, 50),
                    'status' => fake()->randomElement(['pending_market', 'matched', 'cancelled']),
                ]);
            }
        });
    }
}
