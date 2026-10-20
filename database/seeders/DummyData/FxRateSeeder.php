<?php

namespace Database\Seeders\DummyData;

use App\Models\FxRate;
use Illuminate\Database\Seeder;

class FxRateSeeder extends Seeder
{
    public function run(): void
    {
        FxRate::updateOrCreate(
            ['from_currency' => 'NGN', 'to_currency' => 'USD'],
            [
                'base_rate' => 1500,
                'markup_percent' => 1.0,
                'effective_rate' => 1515.0,
            ]
        );
    }
}
