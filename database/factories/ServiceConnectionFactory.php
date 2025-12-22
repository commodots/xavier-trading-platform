<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceConnection>
 */
class ServiceConnectionFactory extends Factory
{
    protected $model = ServiceConnection::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            
            'mode' => $this->faker->randomElement(['live', 'testing', 'dummy']),
            'base_url' => $this->faker->url(),
            'headers' => json_encode(['X-Request-ID' => $this->faker->uuid()]),
            'parameters' => json_encode(['timeout' => 30]),
            'credentials' => json_encode(['api_key' => $this->faker->sha1()]),
            'is_active' => $this->faker->boolean(50),
        ];
    }
}
