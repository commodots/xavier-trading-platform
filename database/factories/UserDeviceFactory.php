<?php

namespace Database\Factories;

use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'device_name' => $this->faker->userAgent(),
            'ip_address' => $this->faker->ipv4(),
            'last_active_at' => now(),
        ];
    }
}
