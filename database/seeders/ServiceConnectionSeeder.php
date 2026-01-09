<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\ServiceConnection;

class ServiceConnectionSeeder extends Seeder
{
    public function run()
    {
        $ngx = Service::where('type', 'ngx')->first();
        if ($ngx) {
            ServiceConnection::firstOrCreate(
                [
                    'service_id' => $ngx->id,
                    'mode' => 'dummy'
                ],
                [
                    'base_url' => 'http://localhost:8000/api/dummy/ngx',
                    'headers' => ['Accept' => 'application/json'],
                    'parameters' => ['lot_size' => 100, 'tick_size' => 0.01],
                    'credentials' => ['sender_comp_id' => 'XAVIER', 'target_comp_id' => 'NGX'],
                    'is_active' => 1
                ]
            );
        }

        $cscs = Service::where('type', 'cscs')->first();
        if ($cscs) {
            ServiceConnection::firstOrCreate(
                [
                    'service_id' => $cscs->id,
                    'mode' => 'dummy'
                ],
                [
                    'base_url' => 'http://localhost:8000/api/dummy/cscs',
                    'headers' => ['Accept' => 'application/json'],
                    'parameters' => ['settlement_cycle' => 'T+3'],
                    'credentials' => ['member_code' => 'XAVIER001'],
                    'is_active' => 1
                ]
            );
        }
    }
}
