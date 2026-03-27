<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // --- NGN Wallet ---
            $ngnBalance = rand(20000, 2000000);
            Wallet::updateOrCreate(
                ['user_id' => $user->id, 'currency' => 'NGN'], // Search criteria
                [
                    'balance' => rand(20000, 2000000),
                    'ngn_cleared' => rand(20000, 2000000),

                    'ngn_uncleared' => 0,

                    'locked' => 0,
                    'account_number' => 'XAV'.rand(10000000, 99999999),
                ]
            );

            // --- USD Wallet ---
            Wallet::updateOrCreate(
                ['user_id' => $user->id, 'currency' => 'USD'], // Search criteria
                [
                    'balance' => rand(10, 800), // Corresponds to the 'balance' column
                    'usd_cleared' => rand(20000, 2000000), // Corresponds to the 'balance' column
                    'usd_uncleared' => 0,
                    'locked' => 0,
                    'account_number' => 'XAV'.rand(10000000, 99999999),
                ]
            );
        }
    }
}
