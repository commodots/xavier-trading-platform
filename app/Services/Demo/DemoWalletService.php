<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;
use Illuminate\Support\Facades\DB;
use App\Models\Demo\DemoWallet;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoLedger;


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
            'currency' => 'NGN', // Ensure currency is set
            'balance' => $amount,
            'ngn_cleared' => $amount, 
            'equity' => $amount
        ]);
    }

    public function reset($userId)
{
    return \DB::transaction(function () use ($userId) {
        // Wipe all simulated activity
        $this->orderRepo->deleteUserOrders($userId);
        
        //Wipe all demo transactions and ledger entries
       DemoTransaction::where('user_id', $userId)->delete();
        DemoLedger::where('user_id', $userId)->delete();

        //Reset all wallets associated with this user
        // We set all balance and cleared columns to 0
        return DemoWallet::where('user_id', $userId)->update([
            'balance' => 0,
            'ngn_cleared' => 0,
            'usd_cleared' => 0,
            'ngn_uncleared' => 0,
            'usd_uncleared' => 0,
            'locked' => 0
        ]);
    });
}
}
