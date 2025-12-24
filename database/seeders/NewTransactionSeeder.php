<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NewTransaction;
use App\Models\TransactionType;
use App\Models\TransactionCharge;
use App\Models\User;
use Carbon\Carbon;
use App\Services\TransactionService;

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


        TransactionCharge::updateOrCreate(['transaction_type' => 'deposit'], ['charge_type' => 'percentage', 'value' => 1.0]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'withdrawal'], ['charge_type' => 'flat', 'value' => 100.0]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'buy_stock'], ['charge_type' => 'percentage', 'value' => 0.5]);
        TransactionCharge::updateOrCreate(['transaction_type' => 'sell_stock'], ['charge_type' => 'percentage', 'value' => 0.5]);


        User::all()->each(function ($user) {
            $txn = NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => 50000,
                'status' => 'completed',
            ]);
            
            TransactionService::applyFees($txn);
        });
    }
}
