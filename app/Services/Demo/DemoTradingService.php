<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;
use App\Services\PriceService;

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

  public function executeTrade($user, $symbol, $marketType, $type, $amount, $frontendQuantity)
  {
    if ($user->trading_mode !== 'demo') {
      throw new \Exception("Switch to demo mode first.");
    }

    $wallet = $this->walletRepo->findByUser($user->id);

    if (!$wallet) {
      throw new \Exception("Demo wallet not funded. Please start your demo account first.");
    }

    $price = $this->priceService->getCurrentPrice($symbol, $marketType);
    $fxRate = 1500;
    $isUsdAsset = in_array(strtolower($marketType), ['international', 'crypto', 'global']);

    if ($type === 'buy') {
        // --- BUY LOGIC: Spend exact Naira amount, calculate true units ---
        if ($isUsdAsset) {
            $usdAmount = $amount / $fxRate;
            $quantity = ($marketType === 'crypto') ? ($usdAmount / $price) : floor($usdAmount / $price);
        } else {
            $quantity = ($marketType === 'crypto') ? ($amount / $price) : floor($amount / $price);
        }
        
        if ($quantity <= 0) {
             throw new \Exception("Amount is too low to purchase even 1 unit at current market price of {$price}.");
        }

        // Re-calculate the final cost based on whole units (so it matches exactly)
        $totalCost = $isUsdAsset ? ($quantity * $price * $fxRate) : ($quantity * $price);

        if ($wallet->balance < $totalCost) {
            throw new \Exception("Insufficient demo balance.");
        }

        $this->walletRepo->updateBalance($wallet, $wallet->balance - $totalCost);

        return $this->orderRepo->create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'market_type' => $marketType,
            'type' => $type,
            'quantity' => $quantity, 
            'price' => $price,
            'total' => $totalCost, 
            'status' => 'closed'
        ]);

    } else {
        // --- SELL LOGIC: Sell exact units, receive true market value ---
        $quantity = $frontendQuantity;
        $totalValue = $isUsdAsset ? ($quantity * $price * $fxRate) : ($quantity * $price);

        $this->walletRepo->updateBalance($wallet, $wallet->balance + $totalValue);

        return $this->orderRepo->create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'market_type' => $marketType,
            'type' => $type,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $totalValue,
            'status' => 'closed'
        ]);
    }
  }


 public function getPortfolio($userId)
  {
    $orders = $this->orderRepo->getUserOrders($userId);
    $wallet = $this->walletRepo->findByUser($userId);

    $holdings = [];

    foreach ($orders as $order) {
      $symbol = $order->symbol;

      if (!isset($holdings[$symbol])) {
        $holdings[$symbol] = [
          'quantity' => 0,
          'market_type' => $order->market_type,
          'avg_price' => $order->price,
        ];
      }

      if ($order->type === 'buy') {
        $holdings[$symbol]['quantity'] += $order->quantity;
      } else {
        $holdings[$symbol]['quantity'] -= $order->quantity;
      }
    }

    $activeHoldings = array_filter($holdings, function ($holding) {
      return $holding['quantity'] > 0;
    });


    $ngxValue = 0;
    $globalUsdValue = 0;
    $cryptoUsdValue = 0;
    $cryptoNgnValue = 0;
    $fixedIncomeValue = 0;
    $fxRate = 1500;

    $formattedHoldings = [];

    
    foreach ($activeHoldings as $symbol => $data) {
        $qty = $data['quantity'];
        $price = $data['avg_price'];
        $mType = strtolower($data['market_type']);
        
        $valNgn = 0;
        $currency = 'NGN';

        if (in_array($mType, ['local', 'ngx'])) {
            $ngxValue += $qty * $price;
            $valNgn = $qty * $price;
        } elseif (in_array($mType, ['international', 'global'])) {
            $globalUsdValue += $qty * $price;
            $valNgn = $qty * $price * $fxRate;
            $currency = 'USD';
        } elseif ($mType === 'crypto') {
            $cryptoUsdValue += $qty * $price;
            $cryptoNgnValue += $qty * $price * $fxRate;
            $valNgn = $qty * $price * $fxRate;
            $currency = 'USD';
        } elseif (in_array($mType, ['fixed_income', 'fixed'])) {
            $fixedIncomeValue += $qty * $price;
            $valNgn = $qty * $price;
        }

        // Format the holding specifically for the frontend table
        $formattedHoldings[] = [
            'symbol' => $symbol,
            'name' => $symbol,
            'quantity' => $qty,
            'avg_price' => $price,
            'avg_price_ngn' => $currency === 'USD' ? $price * $fxRate : $price,
            'market_price' => $price,
            'total_value_ngn' => $valNgn,
            'currency' => $currency,
            'category' => $mType
        ];
    }

    $balance = $wallet ? (float) $wallet->balance : 0;
    
    // Total Equity = Cash Balance + Total Value of all stocks in NGN
    $totalEquity = $balance + $ngxValue + ($globalUsdValue * $fxRate) + $cryptoNgnValue + $fixedIncomeValue;

    // Return the new structure that perfectly matches the live API
    return [
        'wallet_balance' => $balance,
        'total_equity' => $totalEquity,
        'ngx_value' => $ngxValue,
        'global_stocks_value_usd' => $globalUsdValue,
        'crypto_value_ngn' => $cryptoNgnValue,
        'crypto_value_usd' => $cryptoUsdValue,
        'fixed_income_value' => $fixedIncomeValue,
        'portfolio_distribution' => [
            ['label' => 'Wallet', 'value' => $balance],
            ['label' => 'NGX', 'value' => $ngxValue],
            ['label' => 'Global Stocks (USD)', 'value' => $globalUsdValue * $fxRate], // Multiplied by FX so the Pie Chart slices are accurate
            ['label' => 'Crypto (USD)', 'value' => $cryptoNgnValue],
            ['label' => 'Fixed Income', 'value' => $fixedIncomeValue],
        ],
        'holdings' => $formattedHoldings
    ];
  }
}
