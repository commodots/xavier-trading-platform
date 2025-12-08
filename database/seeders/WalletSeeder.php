<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {
            Wallet::create([
                'user_id' => $user->id,
                'ngn_balance' => rand(20000, 2000000),
                'usd_balance' => rand(10, 800),
            ]);
        }
    }
}
