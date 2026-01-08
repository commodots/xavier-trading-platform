<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run()
    {
        $services = [
            ['name' => 'NGX Trading Engine', 'type' => 'ngx_trading'],
            ['name' => 'CSCS Settlement', 'type' => 'cscs_settlement'],
            ['name' => 'Payments Gateway', 'type' => 'payments'],
            ['name' => 'KYC Provider', 'type' => 'kyc'],
            ['name' => 'Notifications', 'type' => 'notifications'],
            ['name' => 'Market Data Feed', 'type' => 'market_data'],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['type' => $service['type']],
                [
                    'name' => $service['name'],
                    'is_active' => false
                ]
            );
        }
    }
}