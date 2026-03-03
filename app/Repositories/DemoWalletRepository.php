<?php

namespace App\Repositories;

use App\Models\Demo\DemoWallet;

class DemoWalletRepository
{
    public function findByUser($userId)
    {
        return DemoWallet::where('user_id', $userId)->first();
    }

    public function createOrUpdate($userId, $data)
    {

        if (isset($data['balance']) && !isset($data['ngn_cleared'])) {
            $data['ngn_cleared'] = $data['balance'];
        }

        return DemoWallet::updateOrCreate(
            ['user_id' => $userId],
            $data
        );
    }

    public function updateBalance($wallet, $balance)
    {
        $wallet->balance = $balance;

        // Sync the cleared column based on currency
        if ($wallet->currency === 'NGN') {
            $wallet->ngn_cleared = $balance;
        } else {
            $wallet->usd_cleared = $balance;
        }

        $wallet->save();
        return $wallet;
    }
    public function findByCurrency($userId, $currency)
    {
        return DemoWallet::where('user_id', $userId)
            ->where('currency', $currency)
            ->first();
    }
}
