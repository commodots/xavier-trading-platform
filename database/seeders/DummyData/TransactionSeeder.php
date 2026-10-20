<?php

namespace Database\Seeders\DummyData;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['deposit', 'withdrawal', 'trade', 'fee', 'transfer'];

        foreach (User::query()->orderBy('id')->get() as $user) {
            foreach (range(1, 10) as $index) {
                $type = $types[($index - 1) % count($types)];
                $asset = ($type === 'trade')
                    ? ['AAPL', 'BTC'][$index % 2]
                    : ['NGN', 'USD'][$index % 2];

                $reference = sprintf('DEMO-TXN-%d-%02d', $user->id, $index);

                Transaction::updateOrCreate(
                    ['reference' => $reference],
                    [
                        'user_id' => $user->id,
                        'type' => $type,
                        'asset' => $asset,
                        'amount' => 2000 + (($user->id * 379 + $index * 811) % 198001),
                        'status' => $index % 2 === 0 ? 'completed' : 'pending',
                    ]
                );
            }
        }
    }
}
