<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CryptoTrade;

class CryptoSeeder extends Seeder
{
    public function run(): void
    {
        $coins = ['BTC', 'ETH', 'SOL', 'BNB', 'DOGE'];

        User::all()->each(function ($user) use ($coins) {
            foreach (range(1, 5) as $i) {
                CryptoTrade::create([
                    'user_id' => $user->id,
                    'coin' => fake()->randomElement($coins),
                    'amount' => fake()->randomFloat(4, 0.001, 1.5),
                    'price' => fake()->randomFloat(2, 100, 90000),
                    'type' => fake()->randomElement(['buy', 'sell']),
                ]);
            }
        });
    }
}
