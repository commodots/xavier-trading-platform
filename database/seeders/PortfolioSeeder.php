<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Portfolio;
use App\Models\User;
use Faker\Factory as Faker;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $users = User::all();

        // Base data for the assets
        $holdings = [
            ['symbol' => 'ZENITH', 'name' => 'Zenith Bank', 'category' => 'local', 'currency' => 'NGN', 'base_price' => 42.20],
            ['symbol' => 'MTNN', 'name' => 'MTN Nigeria', 'category' => 'local', 'currency' => 'NGN', 'base_price' => 235.50],
            ['symbol' => 'TSLA', 'name' => 'Tesla Inc', 'category' => 'foreign', 'currency' => 'USD', 'base_price' => 258.40],
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'category' => 'crypto', 'currency' => 'USD', 'base_price' => 102500.00],
            ['symbol' => 'ETH', 'name' => 'Ethereum', 'category' => 'crypto', 'currency' => 'USD', 'base_price' => 3850.00],
            ['symbol' => 'FGNSB_2027', 'name' => 'FGN Savings Bond 2027', 'category' => 'fixed_income', 'currency' => 'NGN', 'base_price' => 1000.00],
            ['symbol' => 'CP_MTN_I', 'name' => 'MTN Commercial Paper', 'category' => 'fixed_income', 'currency' => 'NGN', 'base_price' => 1000.00],
        ];

        foreach ($users as $user) {
         
            $userHoldings = $faker->randomElements($holdings, rand(3, 5));

            foreach ($userHoldings as $holding) {
                
                $quantity = ($holding['category'] === 'crypto')
                    ? $faker->randomFloat(4, 0.001, 0.5)
                    : $faker->numberBetween(10, 1000);

                
                $variation = $holding['base_price'] * 0.15;
                $avgPrice = $faker->randomFloat(2, $holding['base_price'] - $variation, $holding['base_price'] + $variation);

                Portfolio::updateOrCreate(
                    ['user_id' => $user->id, 'symbol' => $holding['symbol']],
                    [
                        'name' => $holding['name'],
                        'category' => $holding['category'],
                        'quantity' => $quantity,
                        'cleared_quantity' => $quantity, // All seeded holdings are cleared
                        'uncleared_quantity' => 0,
                        'avg_price' => $avgPrice,
                        'market_price' => $holding['base_price'],
                        'currency' => $holding['currency'],
                    ]
                );
            }
        }
    }
}
