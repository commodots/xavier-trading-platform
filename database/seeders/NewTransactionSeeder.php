<?php

namespace Database\Seeders;

use App\Models\NewTransaction;
use App\Models\TransactionCharge;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;

class NewTransactionSeeder extends Seeder
{
    public function run()
    {

        $types = [
            ['name' => 'deposit', 'category' => 'funding'],
            ['name' => 'withdrawal', 'category' => 'funding'],
            ['name' => 'buy_stock', 'category' => 'trading'],
            ['name' => 'sell_stock', 'category' => 'trading'],
            ['name' => 'buy_crypto', 'category' => 'trading'],
            ['name' => 'sell_crypto', 'category' => 'trading'],
            ['name' => 'fx_conversion', 'category' => 'funding'],
        ];

        foreach ($types as $type) {
            TransactionType::updateOrCreate(['name' => $type['name']], $type);
        }

        TransactionCharge::updateOrCreate(['transaction_type' => 'deposit'], ['charge_type' => 'percentage', 'value' => 1.0]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'withdrawal'], ['charge_type' => 'flat', 'value' => 100.0]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'buy_stock'], ['charge_type' => 'percentage', 'value' => 0.5]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'sell_stock'], ['charge_type' => 'percentage', 'value' => 0.5]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'buy_crypto'], ['charge_type' => 'percentage', 'value' => 0.5]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'sell_crypto'], ['charge_type' => 'percentage', 'value' => 0.5]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'fx_conversion'], ['charge_type' => 'percentage', 'value' => 0.05]);

        User::query()
            ->where('email', 'like', 'demo-user-%@example.invalid')
            ->each(function (User $user): void {
                $seedKey = "demo-deposit-{$user->id}";

                $transaction = NewTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('meta->seed_key', $seedKey)
                    ->first();

                if ($transaction) {
                    return;
                }

                $transaction = NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'amount' => 50000,
                    'status' => 'completed',
                    'meta' => [
                        'seed_source' => self::class,
                        'seed_key' => $seedKey,
                    ],
                ]);

                TransactionService::applyFees($transaction);
            });
    }
}
