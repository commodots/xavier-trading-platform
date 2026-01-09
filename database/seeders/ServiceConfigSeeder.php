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
                'base_url' => null,
                'headers' => null,
                'params' => json_encode(['allow_short_sell'=> false,'market_hours'=> '09:30-14:30']),
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
                'params' => ['auto_settle' => true, 'partial_allowed' => false],
                'credentials' => null,
                'is_active' => true,
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
