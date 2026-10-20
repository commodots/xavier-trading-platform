<?php

namespace Database\Seeders;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();

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
            $userHoldings = array_slice($holdings, 0, 3 + ($user->id % 3));

            foreach ($userHoldings as $index => $holding) {

                $quantity = ($holding['category'] === 'crypto')
                    ? round(0.001 + (($user->id * 17 + $index * 13) % 500) / 1000, 4)
                    : 10 + (($user->id * 31 + $index * 17) % 991);

                $variationPercent = (($user->id * 7 + $index * 11) % 31) - 15;
                $avgPrice = round($holding['base_price'] * (1 + $variationPercent / 100), 2);

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
