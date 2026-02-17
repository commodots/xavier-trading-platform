<?php

namespace App\Services;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;
use App\Services\Demo\PriceService;


class DemoTradingService
{
  protected $walletRepo;
  protected $orderRepo;
  protected $priceService;

  public function __construct(
    DemoWalletRepository $walletRepo,
    DemoOrderRepository $orderRepo,
    PriceService $priceService
  ) {
    $this->walletRepo = $walletRepo;
    $this->orderRepo = $orderRepo;
    $this->priceService = $priceService;
  }

  public function executeTrade($user, $symbol, $marketType, $type, $quantity)
  {
    if ($user->trading_mode !== 'demo') {
      throw new \Exception("Switch to demo mode first.");
    }

    $wallet = $this->walletRepo->findByUser($user->id);

    if (!$wallet) {
      throw new \Exception("Demo wallet not funded. Please start your demo account first.");
    }

    $price = $this->priceService->getCurrentPrice($symbol, $marketType);
    $total = $price * $quantity;

    if ($type === 'buy' && $wallet->balance < $total) {
      throw new \Exception("Insufficient demo balance to purchase {$quantity} units of {$symbol}.");
    }

    $newBalance = $type === 'buy'
      ? $wallet->balance - $total
      : $wallet->balance + $total;

    $this->walletRepo->updateBalance($wallet, $newBalance);

    return $this->orderRepo->create([
      'user_id' => $user->id,
      'symbol' => $symbol,
      'market_type' => $marketType,
      'type' => $type,
      'quantity' => $quantity,
      'price' => $price,
      'total' => $total,
      'status' => 'closed'
    ]);
  }

  public function getPortfolio($userId)
  {
    $orders = $this->orderRepo->getUserOrders($userId);

    $holdings = [];

    foreach ($orders as $order) {
      if (!isset($holdings[$order->symbol])) {
        $holdings[$order->symbol] = [
          'quantity' => 0,
          'market_type' => $order->market_type
        ];
      }

      $holdings[$order->symbol] +=
        $order->type === 'buy'
        ? $order->quantity
        : -$order->quantity;
    }

    return array_filter($holdings, fn($h) => $h['quantity'] > 0);
  }
}
