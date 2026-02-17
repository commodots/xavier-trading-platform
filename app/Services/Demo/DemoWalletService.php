<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;

class DemoWalletService
{
    protected $walletRepo;

    public function __construct(DemoWalletRepository $walletRepo)
    {
        $this->walletRepo = $walletRepo;
    }

    public function fund($userId, $amount)
    {
        return $this->walletRepo->createOrUpdate($userId, [
            'balance' => $amount,
            'equity' => $amount
        ]);
    }

    public function reset($userId)
    {
        return $this->walletRepo->createOrUpdate($userId, [
            'balance' => 0,
            'equity' => 0
        ]);
    }
}