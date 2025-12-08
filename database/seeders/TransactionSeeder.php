<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\User;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {

            foreach (range(1, rand(5,15)) as $i) {
                Transaction::create([
                    'user_id' => $user->id,
                    'type' => ['Deposit','Withdrawal','Buy','Sell'][rand(0,3)],
                    'asset' => ['NGN','USD','ZENITH','AAPL','BTC'][rand(0,4)],
                    'amount' => rand(2000, 200000),
                    'status' => ['completed','pending'][rand(0,1)],
                ]);
            }
        }
    }
}
