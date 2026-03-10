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
            'currency' => 'NGN',
            'balance' => 1000000,
            'cleared_balance' => 1000000,
            'uncleared_balance' => 0,
            'locked' => 0,
        ];
    }
}
