<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run()
    {
        $services = [
            ['name' => 'NGX FIX Gateway', 'type' => 'ngx', 'is_active' => true],
            ['name' => 'CSCS Settlement Engine', 'type' => 'cscs', 'is_active' => true],
            ['name' => 'Payments Gateway', 'type' => 'payments', 'is_active' => false],
            ['name' => 'KYC Provider', 'type' => 'kyc', 'is_active' => false],
            ['name' => 'Notifications', 'type' => 'notifications', 'is_active' => false],
            ['name' => 'Market Data Feed', 'type' => 'market_data', 'is_active' => false],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['type' => $service['type']],
                [
                    'name' => $service['name'],
                    'is_active' => $service['is_active'] ?? false
                ]
            );
        }
    }
}