<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceConfig;

class ServiceConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'service' => 'NGX',
                'type' => 'ngx',
                'mode' => 'dummy',
                'base_url' => 'https://dummy-ngx.local',
                'headers' => ['Accept' => 'application/json'],
                'params' => ['lot_size' => 100],
                'credentials' => null,
                'is_active' => true,
            ],
            [
                'service' => 'Paystack',
                'type' => 'payment',
                'mode' => 'test',
                'base_url' => 'https://api.paystack.co',
                'headers' => ['Authorization' => 'Bearer sk_test_xxx'],
                'params' => ['currency' => 'NGN'],
                'credentials' => ['secret_key' => 'sk_test_xxx'],
                'is_active' => true,
            ],
            [
                'service' => 'CSCS',
                'type' => 'cscs',
                'mode' => 'dummy',
                'base_url' => null,
                'headers' => null,
                'params' => ['settlement_days' => 'T+3'],
                'credentials' => null,
                'is_active' => false,
            ],
        ];

        foreach ($configs as $config) {
            ServiceConfig::updateOrCreate(
                ['service' => $config['service']],
                $config
            );
        }
    }
}
