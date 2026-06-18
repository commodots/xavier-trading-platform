<?php

namespace Database\Seeders;

use App\Models\FxSetting;
use Illuminate\Database\Seeder;

class FxSettingSeeder extends Seeder
{
    public function run(): void
    {
        FxSetting::create([
            'provider' => 'manual',
            'enabled' => true,
            'auto_convert_stocks' => false,
        ]);
    }
}