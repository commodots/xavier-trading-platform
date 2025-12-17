<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;


class WalletSeeder extends Seeder
{
    public function run(): void
    {
       
        $users = User::all();

        
        foreach ($users as $user) {
            
            // --- NGN Wallet ---
            Wallet::create([
                'user_id' => $user->id,
                'currency' => 'NGN', // Corresponds to the 'currency' column
                'balance' => rand(20000, 2000000), // Corresponds to the 'balance' column
                'locked' => 0,
            ]);

            // --- USD Wallet ---
            Wallet::create([
                'user_id' => $user->id,
                'currency' => 'USD', // Corresponds to the 'currency' column
                'balance' => rand(10, 800), // Corresponds to the 'balance' column
                'locked' => 0,
            ]);

        
        }
    }
}