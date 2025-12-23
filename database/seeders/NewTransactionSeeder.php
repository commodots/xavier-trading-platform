<?php

// database/seeders/TransactionSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NewTransaction;
use App\Models\TransactionType;
use App\Models\TransactionCharge;
use App\Models\User;
use Carbon\Carbon;

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
        ];

        foreach ($types as $type) {
            TransactionType::updateOrCreate(['name' => $type['name']], $type);
        }


        TransactionCharge::updateOrCreate(
            ['transaction_type' => 'deposit'],
            ['flat_fee' => 0, 'percentage' => 0]
        );
        TransactionCharge::updateOrCreate(
            ['transaction_type' => 'withdrawal'],
            ['flat_fee' => 100, 'percentage' => 1.5]
        );
        TransactionCharge::updateOrCreate(
            ['transaction_type' => 'buy_stock'],
            ['flat_fee' => 50, 'percentage' => 1.0]
        );


        $users = User::all();
        foreach ($users as $user) {
            for ($i = 0; $i < 10; $i++) {
                $amount = rand(5000, 50000);
                $type = collect(['deposit', 'buy_stock', 'withdrawal'])->random();


                $charge = TransactionCharge::calculate($type, $amount);

                NewTransaction::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'charge' => $charge,
                    'net_amount' => ($type === 'deposit') ? ($amount - $charge) : ($amount + $charge),
                    'status' => 'completed',
                    'created_at' => Carbon::now()->subDays(rand(0, 30)),
                ]);
            }
        }
    }
}
