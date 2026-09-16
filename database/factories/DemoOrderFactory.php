<?php

namespace Database\Factories;

use App\Models\Demo\DemoOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoOrder>
 */
class DemoOrderFactory extends Factory
{
    protected $model = DemoOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(['MTNN', 'GTCO', 'ZENITHBANK']),
            'market' => 'NGX',
            'side' => $this->faker->randomElement(['buy', 'sell']),
            'status' => 'filled',
            'amount' => $this->faker->numberBetween(1000, 50000),
            'quantity' => $this->faker->numberBetween(10, 1000),
            'price' => $this->faker->randomFloat(2, 10, 200),
        ];
    }
}
