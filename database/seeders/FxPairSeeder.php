<?php

namespace Database\Seeders;

use App\Models\FxPair;
use Illuminate\Database\Seeder;

class FxPairSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = [
            [
                'base_currency' => 'USD',
                'quote_currency' => 'NGN',
                'buy_rate' => 1385,
                'sell_rate' => 1370,
                'active' => true,
            ],
            [
                'base_currency' => 'GBP',
                'quote_currency' => 'NGN',
                'buy_rate' => 1850,
                'sell_rate' => 1825,
                'active' => true,
            ],
            [
                'base_currency' => 'EUR',
                'quote_currency' => 'NGN',
                'buy_rate' => 1600,
                'sell_rate' => 1580,
                'active' => true,
            ],
        ];

        foreach ($pairs as $pair) {
            FxPair::create($pair);
        }
    }
}