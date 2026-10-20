<?php

namespace Database\Seeders;

use App\Models\FxConfig;
use Illuminate\Database\Seeder;

class FxConfigSeeder extends Seeder
{
    public function run(): void
    {
        FxConfig::updateOrCreate(
            ['id' => 1],
            [
                'min_markup' => 1,
                'max_markup' => 5,
                'target_margin_percent' => 2,
                'volatility_threshold' => 3,
            ]
        );
    }
}
