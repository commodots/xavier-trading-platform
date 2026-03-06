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
        return DB::transaction(function () use ($userId, $amount) {
            $wallet = DemoWallet::updateOrCreate(
                ['user_id' => $userId, 'currency' => 'NGN'],
                ['status' => 'active'] 
            );

            $wallet->increment('balance', $amount);
            $wallet->increment('ngn_cleared', $amount);

            
            DemoTransaction::create([
                'user_id'    => $userId,
                'type'       => 'deposit',
                'amount'     => $amount,
                'currency'   => 'NGN',
                'status'     => 'completed',
                'net_amount' => $amount,
                'meta'       => [
                    'note' => 'Demo Account Refill',
                    'is_demo' => true
                ]
            ]);

            return $wallet;
        });
    }

    public function reset($userId)
    {
        return DB::transaction(function () use ($userId) {
            
            $this->orderRepo->deleteUserOrders($userId);

            DemoTransaction::where('user_id', $userId)->delete();
            DemoLedger::where('user_id', $userId)->delete();

            return DemoWallet::where('user_id', $userId)->update([
                'balance'       => 0,
                'ngn_cleared'   => 0,
                'usd_cleared'   => 0,
                'ngn_uncleared' => 0,
                'usd_uncleared' => 0,
                'locked'        => 0
            ]);
        });
    }
}
