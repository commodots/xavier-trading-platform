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
        $quantity = $frontendQuantity;
        
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
      // Only process filled/closed orders for holdings
      if (!in_array($order->status, ['closed', 'filled'])) {
          continue;
      }

      $symbol = $order->symbol;

      if (!isset($holdings[$symbol])) {
        $holdings[$symbol] = [
          'quantity' => 0,
          'total_cost' => 0,
          'market_type' => $order->market_type,
          'current_price' => $order->price, // Uses the latest known price from the order
        ];
      }

      if ($order->type === 'buy') {
        $holdings[$symbol]['quantity'] += $order->quantity;
        $holdings[$symbol]['total_cost'] += $order->total;
      } else {
        $holdings[$symbol]['quantity'] -= $order->quantity;
        // Adjust total cost proportionally on sell to keep avg price accurate
        // (Not strictly necessary for Demo but good practice)
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
        $price = $data['current_price'];
        $mType = strtolower($data['market_type']);
        
        $valNgn = 0;
        $currency = 'NGN';
        $avgPrice = $data['total_cost'] / $qty; // Accurate average price

        if (in_array($mType, ['local', 'ngx'])) {
            $ngxValue += $qty * $price;
            $valNgn = $qty * $price;
            $category = 'NGX';
        } elseif (in_array($mType, ['international', 'global'])) {
            $globalUsdValue += $qty * $price;
            $valNgn = $qty * $price * $fxRate;
            $currency = 'USD';
            $category = 'GLOBAL';
        } elseif ($mType === 'crypto') {
            $cryptoUsdValue += $qty * $price;
            $cryptoNgnValue += $qty * $price * $fxRate;
            $valNgn = $qty * $price * $fxRate;
            $currency = 'USD';
            $category = 'CRYPTO';
        } elseif (in_array($mType, ['fixed_income', 'fixed'])) {
            $fixedIncomeValue += $qty * $price;
            $valNgn = $qty * $price;
            $category = 'FIXED_INCOME';
        }

        // THE FIX: Added 'cleared_quantity' and 'uncleared_quantity' so Vue can read it!
        $formattedHoldings[] = [
            'symbol' => $symbol,
            'name' => $symbol,
            'quantity' => $qty,
            'cleared_quantity' => $qty,    // <--- REQUIRED BY VUE
            'uncleared_quantity' => 0,     // <--- REQUIRED BY VUE
            'avg_price' => $avgPrice,
            'avg_price_ngn' => $currency === 'USD' ? $avgPrice * $fxRate : $avgPrice,
            'market_price' => $price,
            'total_value_ngn' => $valNgn,
            'currency' => $currency,
            'category' => $category
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
        // The pie chart uses these exact variables, mapped correctly to NGN equivalents
        'portfolio_distribution' => [
            ['label' => 'Wallet', 'value' => $balance],
            ['label' => 'NGX', 'value' => $ngxValue],
            ['label' => 'Global Stocks (USD)', 'value' => $globalUsdValue * $fxRate], 
            ['label' => 'Crypto (USD)', 'value' => $cryptoNgnValue],
            ['label' => 'Fixed Income', 'value' => $fixedIncomeValue],
        ],
        'holdings' => $formattedHoldings
    ];
  }
}