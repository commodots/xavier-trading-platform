<?php

namespace Database\Factories\Demo;

use App\Models\Demo\DemoWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Demo\DemoWallet>
 */
class DemoWalletFactory extends Factory
{
    protected $model = DemoWallet::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'balance_ngn' => 1000000,
            'ngn_cleared' => 1000000,
            'ngn_uncleared' => 0,
            'ngn_locked' => 0,
            'balance_usd' => 10000,
            'usd_cleared' => 10000,
            'usd_uncleared' => 0,
            'usd_locked' => 0,
        ];
    }
}
