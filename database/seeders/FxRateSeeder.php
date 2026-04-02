<?php

namespace Database\Seeders;

use App\Models\FxRate;
use Illuminate\Database\Seeder;

class FxRateSeeder extends Seeder
{
    public function run(): void
    {
        FxRate::create([
            'from_currency' => 'NGN',
            'to_currency' => 'USD',
            'base_rate' => 1500, // 1500 NGN = 1 USD
            'markup_percent' => 1.0,
            'effective_rate' => 1515.0,
        ]);
    }
}
