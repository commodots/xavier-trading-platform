<?php

namespace Database\Seeders\DummyData;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            Wallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => 'NGN'],
                [
                    'balance' => 20000 + ($user->id * 1000),
                    'ngn_cleared' => 20000 + ($user->id * 1000),
                    'ngn_uncleared' => 0,
                    'locked' => 0,
                    'account_number' => 'XAVN'.str_pad((string) $user->id, 12, '0', STR_PAD_LEFT),
                ]
            );

            Wallet::firstOrCreate(
                ['user_id' => $user->id, 'currency' => 'USD'],
                [
                    'balance' => 100 + $user->id,
                    'usd_cleared' => 100 + $user->id,
                    'usd_uncleared' => 0,
                    'locked' => 0,
                    'account_number' => 'XAVU'.str_pad((string) $user->id, 12, '0', STR_PAD_LEFT),
                ]
            );
        }
    }
}
