<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;
use App\Services\PriceService;
use App\Models\Demo\DemoWallet;
use Illuminate\Support\Facades\DB;

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

  public function executeTrade($user, array $data)
  {
    if ($user->trading_mode !== 'demo') {
      throw new \Exception("Switch to demo mode first.");
    }

    return DB::transaction(function () use ($user, $data) {
      $symbol = $data['symbol'];
      $market = $data['market'];
      $type = $data['side'];
      $amount = $data['amount'];
      $price = $data['market_price'];

      $currency = ($market === "NGX" || $market === "local") ? "NGN" : "USD";
      $wallet = $this->walletRepo->findByCurrency($user->id, $currency);

      if (!$wallet) {
        throw new \Exception("Demo {$currency} wallet not found.");
      }

      $quantity = ($market === 'CRYPTO') ? ($amount / $price) : floor($amount / $price);

      if ($quantity <= 0) {
        throw new \Exception("Amount too low to purchase units.");
      }

      $totalCost = $quantity * $price;
      $clearedCol = ($currency === 'NGN') ? 'ngn_cleared' : 'usd_cleared';

      if ($type === 'buy') {
        if ($wallet->$clearedCol < $totalCost) {
          throw new \Exception("Insufficient demo {$currency} balance.");
        }

        // Update BOTH balance and cleared column to match Live logic
        $wallet->decrement('balance', $totalCost);
        $wallet->decrement($clearedCol, $totalCost);
      } else {
        // Sell verification logic (Simplified for readability)
        $currentHolding = $this->calculateHoldings($user->id, $symbol);

        if ($currentHolding < $quantity) {
          throw new \Exception("Insufficient holdings. You have {$currentHolding} units.");
        }

        $wallet->increment('balance', $totalCost);
        $wallet->increment($clearedCol, $totalCost);
      }

      return $this->orderRepo->create([
        'user_id'      => $user->id,
        'symbol'       => $symbol,
        'market'       => $market,
        'side'         => $type,
        'type'         => 'market',
        'quantity'     => $quantity,
        'price'        => $price,
        'amount'       => $totalCost,
        'market_price' => $price,
        'status'       => 'filled',
        'currency'     => $currency,
      ]);
    });
  }

  private function calculateHoldings($userId, $symbol)
  {
    $orders = $this->orderRepo->getUserOrders($userId);
    $total = 0;
    foreach ($orders as $order) {
      if ($order->symbol === $symbol && in_array($order->status, ['closed', 'filled'])) {
        $order->side === 'buy' ? $total += $order->quantity : $total -= $order->quantity;
      }
    }
    return $total;
  }

  public function getPortfolio($userId)
  {
    $orders = $this->orderRepo->getUserOrders($userId);
    $wallets = DemoWallet::where('user_id', $userId)->get();
    $fxRate = 1500;

    $holdings = [];
    foreach ($orders as $order) {
      if (!in_array(strtolower($order->status), ['closed', 'filled'])) continue;

      $symbol = $order->symbol;
      if (!isset($holdings[$symbol])) {
        $holdings[$symbol] = [
          'quantity'      => 0,
          'total_cost'    => 0,
          'market'        => strtoupper($order->market),
          'current_price' => $order->price,
        ];
      }

      if (strtolower($order->side) === 'buy') {
        $holdings[$symbol]['quantity']   += (float)$order->quantity;
        $holdings[$symbol]['total_cost'] += (float)$order->amount;
      } else {
        $holdings[$symbol]['quantity']   -= (float)$order->quantity;
      }
    }

    $ngxValue = 0;
    $globalValueNgn = 0;
    $cryptoValueNgn = 0;
    $fixedIncomeValue = 0;
    $formattedHoldings = [];

    foreach (array_filter($holdings, fn($h) => $h['quantity'] > 0) as $symbol => $data) {
      $qty = $data['quantity'];
      $price = $data['current_price'];
      $mType = strtoupper($data['market']);
      $avgPrice = $qty > 0 ? ($data['total_cost'] / $qty) : 0;
      $totalVal = $qty * $price;

      $isUsd = in_array($mType, ['INTERNATIONAL', 'GLOBAL', 'CRYPTO']);
      $multiplier = $isUsd ? $fxRate : 1;
      $valNgn = $totalVal * $multiplier;

      // Track category sums for the chart
      if (in_array($mType, ['LOCAL', 'NGX'])) {
        $ngxValue += $valNgn;
      } elseif (in_array($mType, ['INTERNATIONAL', 'GLOBAL'])) {
        $globalValueNgn += $valNgn;
      } elseif ($mType === 'CRYPTO') {
        $cryptoValueNgn += $valNgn;
      } elseif (in_array($mType, ['FIXED_INCOME', 'FIXED'])) {
        $fixedIncomeValue += $valNgn;
      }

      $formattedHoldings[] = [
        'symbol' => $symbol,
        'name' => $symbol,
        'quantity' => $qty,
        'cleared_quantity' => $qty,
        'uncleared_quantity' => 0,
        'avg_price' => $avgPrice,
        'market_price' => $price,
        'total_value' => $totalVal,
        'total_value_ngn' => $valNgn,
        'avg_price_ngn' => $avgPrice * $multiplier,
        'currency' => $isUsd ? 'USD' : 'NGN',
        'category' => strtoupper($mType)
      ];
    }

    $ngnWallet = $wallets->where('currency', 'NGN')->first();
    $usdWallet = $wallets->where('currency', 'USD')->first();

    // Use ngn_cleared for the dashboard "Cash" value
    $ngnBal = (float)($ngnWallet->ngn_cleared ?? 0);
    $usdBal = (float)($usdWallet->usd_cleared ?? 0);
    $totalCashNgn = $ngnBal + ($usdBal * $fxRate);

    // Total Equity = Cash Balance + Total Value of all stocks in NGN
    $totalEquity = $totalCashNgn + $ngxValue + $globalValueNgn + $cryptoValueNgn + $fixedIncomeValue;

    return [
      'success' => true,
      'trading_mode' => 'demo',
      'wallet_balance' => $totalCashNgn,
      'total_equity' => $totalEquity,
      'holdings' => $formattedHoldings,
      'ngx_value' => $ngxValue,
      'global_stocks_value_usd' => $globalValueNgn * $fxRate,
      'crypto_value_ngn' => $cryptoValueNgn,
      'crypto_value_usd' => $cryptoValueNgn * $fxRate,
      'fixed_income_value' => $fixedIncomeValue,
      'portfolio_distribution' => [
        ['label' => 'Wallet', 'value' => $totalCashNgn],
        ['label' => 'NGX', 'value' => $ngxValue],
        ['label' => 'Global Stocks (USD)', 'value' => $globalValueNgn],
        ['label' => 'Crypto (USD)', 'value' => $cryptoValueNgn],
        ['label' => 'Fixed Income', 'value' => $fixedIncomeValue],
      ]
    ];
  }
}
