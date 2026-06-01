<?php

namespace Database\Factories;

use App\Models\KycSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KycSetting>
 */
class KycSettingFactory extends Factory
{
    protected $model = KycSetting::class;

    public function definition(): array
    {
        return [
            'tier' => $this->faker->numberBetween(1, 3),
            'tier_name' => 'Tier ' . $this->faker->numberBetween(1, 3),
            'daily_limit' => $this->faker->numberBetween(10000, 1000000),
            'required_documents' => [],
        ];
    }
}
