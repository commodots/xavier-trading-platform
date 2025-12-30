<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Portfolio;
use App\Models\User;

class PortfolioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        $holdings = [
            ['symbol' => 'ZENITH', 'name' => 'Zenith Bank', 'category' => 'local', 'quantity' => 100, 'avg_price' => 45.20, 'market_price' => 50.50, 'currency' => 'NGN'],
            ['symbol' => 'MTN', 'name' => 'MTN Nigeria', 'category' => 'local', 'quantity' => 50, 'avg_price' => 120.00, 'market_price' => 135.00, 'currency' => 'NGN'],
            ['symbol' => 'TSLA', 'name' => 'Tesla Inc', 'category' => 'foreign', 'quantity' => 5, 'avg_price' => 160.00, 'market_price' => 175.40, 'currency' => 'USD'],
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'category' => 'crypto', 'quantity' => 0.021, 'avg_price' => 18000000, 'market_price' => 24761904, 'currency' => 'USD'],
        ];

        foreach ($users as $user) {
            foreach ($holdings as $holding) {
                Portfolio::updateOrCreate(array_merge($holding, [
                    'user_id' => $user->id
                ]));
            }
        }
    }
}
