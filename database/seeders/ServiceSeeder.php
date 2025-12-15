<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['name' => 'NGX Trading Engine', 'type' => 'ngx'],
            ['name' => 'Crypto Exchange', 'type' => 'crypto'],
            ['name' => 'FX Provider', 'type' => 'fx'],
            ['name' => 'Payment Gateway', 'type' => 'payment'],
            ['name' => 'CSCS Settlement', 'type' => 'settlement'],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(['type' => $service['type']], $service);
        }
    }
}
