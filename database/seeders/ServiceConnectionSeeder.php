<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\ServiceConnection;

class ServiceConnectionSeeder extends Seeder
{
    public function run()
    {
        $services = Service::all();

        foreach ($services as $service) {
            ServiceConnection::firstOrCreate([
                'service_id' => $service->id,
                'mode' => 'dummy',
            ], [
                'base_url' => 'http://localhost/dummy',
                'headers' => [],
                'parameters' => [],
                'credentials' => [],
                'is_active' => true,
            ]);
        }
    }
}