<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $allowedTypes = ['deposit', 'withdrawal', 'trade', 'fee', 'transfer'];
        foreach (User::all() as $user) {

            foreach (range(1, rand(5, 15)) as $i) {
                $seederTypes = ['deposit', 'withdrawal', 'trade', 'trade'];
                $type = $seederTypes[array_rand($seederTypes)];
                $asset = ($type === 'trade')
                    ? ['AAPL', 'BTC'][rand(0, 1)]
                    : ['NGN', 'USD'][rand(0, 1)];
                Transaction::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'asset' => $asset,
                    'amount' => rand(2000, 200000),
                    'status' => ['completed', 'pending'][rand(0, 1)],
                    'reference' => 'TXN-' . Str::ulid(),
                ]);
            }
        }
    }
}
