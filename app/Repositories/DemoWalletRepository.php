<?php

namespace App\Repositories;

use App\Models\DemoWallet;

class DemoWalletRepository
{
    public function findByUser($userId)
    {
        return DemoWallet::where('user_id', $userId)->first();
    }

    public function createOrUpdate($userId, $data)
    {
        return DemoWallet::updateOrCreate(
            ['user_id' => $userId],
            $data
        );
    }

    public function updateBalance($wallet, $balance)
    {
        $wallet->balance = $balance;
        $wallet->save();
        return $wallet;
    }
}