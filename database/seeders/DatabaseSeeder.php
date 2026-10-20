<?php

namespace Database\Seeders;

use Database\Seeders\DummyData\DummyDataSeeder;
use Database\Seeders\Production\ProductionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->call(DummyDataSeeder::class);
        }
    }
}
