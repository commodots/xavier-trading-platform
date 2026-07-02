<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
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
            'user_id' => User::factory(),
            'currency' => 'NGN',
            'balance' => 0,
            'ngn_cleared' => 0,
            'ngn_uncleared' => 0,
            'usd_cleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0,
            'status' => 'active',
        ];
    }
}
