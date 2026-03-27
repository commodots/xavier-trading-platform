<?php

namespace Database\Seeders;

use App\Models\CryptoAddress;
use App\Models\User;
use Illuminate\Database\Seeder;

class CryptoSeeder extends Seeder
{
    public function run(): void
    {
        // Create crypto addresses for existing users
        User::all()->each(function ($user) {
            if (! $user->cryptoAddresses()->where('blockchain', 'TRON')->exists()) {
                // For demo, create fake address 
                CryptoAddress::create([
                    'user_id' => $user->id,
                    'blockchain' => 'TRON',
                    'address' => 'T'.strtoupper(substr(md5($user->id.'tron'), 0, 34)),
                    'private_key' => encrypt('demo_private_key_'.$user->id), // Fake for demo
                ]);
            }
        });
    }
}
