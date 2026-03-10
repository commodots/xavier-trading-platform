<?php

namespace Database\Factories;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Wallet::class;
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(), // Creates a user if one isn't provided
            'currency' => 'NGN',
            'balance' => 0,
            'cleared_balance' => 0,
            'uncleared_balance' => 0,
            'locked' => 0,
        ];
    }
}
