<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;

class DemoWalletService
{
    protected $walletRepo;
    protected $orderRepo;

    public function __construct(
        DemoWalletRepository $walletRepo,
        DemoOrderRepository $orderRepo 
    ) {
        $this->walletRepo = $walletRepo;
        $this->orderRepo = $orderRepo;
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
        $this->orderRepo->deleteUserOrders($userId);
        
        return $this->walletRepo->createOrUpdate($userId, [
            'balance' => 0,
            'equity' => 0
        ]);
    }
}