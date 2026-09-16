<?php

namespace Database\Factories;

use App\Models\Demo\DemoPortfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoPortfolio>
 */
class DemoPortfolioFactory extends Factory
{
    protected $model = DemoPortfolio::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(['MTNN', 'GTCO', 'ZENITHBANK']),
            'cleared_quantity' => $this->faker->numberBetween(10, 1000),
            'average_price' => $this->faker->randomFloat(2, 10, 200),
        ];
    }
}
