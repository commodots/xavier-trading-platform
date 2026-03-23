<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;
use App\Services\PriceService;
use App\Models\Demo\DemoWallet;
use App\Models\Demo\DemoOrder;
use App\Models\Demo\DemoPortfolio;
use App\Models\Demo\DemoTransaction;
use App\Models\Demo\DemoLedger;
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
      $rawMarket = strtoupper($data['market']);

      if (str_contains($rawMarket, 'CRYPTO')) {
        $market = 'crypto';
      } elseif (str_contains($rawMarket, 'FIXED')) {
        $market = 'fixed_income';
      } elseif (str_contains($rawMarket, 'GLOBAL') || str_contains($rawMarket, 'FOREIGN')) {
        $market = 'foreign';
      } else {
        $market = 'local';
      }

      $type = $data['side'];
      $amount = $data['amount'];
      $price = $data['market_price'];
      $fxRate = 1500;

      $wallet = $this->walletRepo->findByCurrency($user->id, "NGN");
      if (!$wallet) {
        throw new \Exception("Demo NGN wallet not found.");
      }

      $isUsdMarket = in_array($market, ["foreign", "crypto"]);

      if ($isUsdMarket) {
        $quantity = ($market === 'crypto')
          ? ($amount / ($price * $fxRate))
          : floor($amount / ($price * $fxRate));
      } else {
        $quantity = floor($amount / $price);
      }

      if ($quantity <= 0) {
        throw new \Exception("Amount too low to purchase units.");
      }

      $totalCost = $quantity * $price * ($isUsdMarket ? $fxRate : 1);

      // Track balance before for the Ledger
      $balanceBefore = (float)$wallet->balance;

      if ($type === 'buy') {
        if ($wallet->ngn_cleared < $totalCost) {
          throw new \Exception("Insufficient demo NGN balance.");
        }
        $wallet->decrement('balance', $totalCost);
        $wallet->decrement('ngn_cleared', $totalCost);
        $transactionType = match ($market) {
          'crypto' => 'buy_crypto',
          'foreign' => 'buy_global',
          default => 'buy_stock',
        };
      } else {
        $wallet->increment('balance', $totalCost);
        $wallet->increment('ngn_cleared', $totalCost);
        $transactionType = match ($market) {
          'crypto' => 'sell_crypto',
          'foreign' => 'sell_global',
          default => 'sell_stock',
        };
      }

      $order = $this->orderRepo->create([
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
        'currency'     => 'NGN',
      ]);

      DemoTransaction::create([
        'user_id'    => $user->id,
        'amount'     => $totalCost,
        'type'       => $transactionType, 
        'status'     => 'completed',
        'currency'   => 'NGN',
        'is_cleared' => true,
        'meta'       => json_encode([
          'symbol'   => $symbol,
          'quantity' => $quantity,
          'price'    => $price
        ]),
      ]);

      DemoLedger::create([
        'user_id'   => $user->id,
        'currency'  => 'NGN',
        'amount'    => $totalCost,
        'type'      => strtoupper($transactionType),
        'status'    => 'completed',
        'reference' => $order->id,
        'meta'      => json_encode(['balance_after' => $wallet->balance]),
      ]);

      // Update Portfolio Snapshot to satisfy database assertions and persistent state
      $portfolio = DemoPortfolio::firstOrCreate(
          ['user_id' => $user->id, 'symbol' => $symbol],
          [
              'name' => $symbol,
              'quantity' => 0,
              'cleared_quantity' => 0,
              'uncleared_quantity' => 0,
              'avg_price' => 0,
              'market_price' => $price,
              'currency' => $isUsdMarket ? 'USD' : 'NGN',
              'category' => $market
          ]
      );

      if ($type === 'buy') {
          $portfolio->increment('quantity', $quantity);
          $portfolio->increment('cleared_quantity', $quantity);
      } else {
          $portfolio->decrement('quantity', $quantity);
          $portfolio->decrement('cleared_quantity', $quantity);
      }

      return $order; 
    });
  }

  public function getPortfolio($userId)
  {
    $portfolios = DemoPortfolio::where('user_id', $userId)->get();
    $wallets = DemoWallet::where('user_id', $userId)->get();
    $fxRate = 1500;
    
    $ngxValue = 0;
    $globalValueNgn = 0;
    $cryptoValueNgn = 0;
    $fixedIncomeValue = 0;
    $formattedHoldings = [];

    foreach ($portfolios as $p) {
      if ($p->quantity < 0.00000001) continue;

      $qty = (float)$p->quantity;
      $price = (float)$p->market_price;
      $mType = $p->category ?? 'local'; // Default to local if not set
      
      $isUsd = in_array($mType, ['foreign', 'crypto', 'global']) || $p->currency === 'USD';
      $multiplier = $isUsd ? $fxRate : 1;

      $avgPriceNgn = (float)$p->avg_price * ($isUsd ? $fxRate : 1); 
      $totalVal = $qty * $price;
      $valNgn = $totalVal * $multiplier;

      if ($mType === 'local') $ngxValue += $valNgn;
      elseif ($mType === 'foreign' || $mType === 'global') $globalValueNgn += $valNgn;
      elseif ($mType === 'crypto') $cryptoValueNgn += $valNgn;
      elseif ($mType === 'fixed_income') $fixedIncomeValue += $valNgn;

      $formattedHoldings[] = [
        'symbol'             => $p->symbol,
        'name'               => $p->name ?? $p->symbol,
        'quantity'           => round($qty, 8),
        'cleared_quantity'   => round((float)$p->cleared_quantity, 8),
        'uncleared_quantity' => round((float)$p->uncleared_quantity, 8),
        'avg_price'          => round((float)$p->avg_price, 2),
        'market_price'       => round($price, 2),
        'total_value'        => round($totalVal, 2),
        'total_value_ngn'    => round($valNgn, 2),
        'avg_price_ngn'      => round($avgPriceNgn, 2),
        'currency'           => $p->currency ?? ($isUsd ? 'USD' : 'NGN'),
        'category'           => $mType
      ];
    }

    $ngnWallet = $wallets->where('currency', 'NGN')->first();
    $usdWallet = $wallets->where('currency', 'USD')->first();
    $totalCashNgn = ($ngnWallet ? (float)$ngnWallet->ngn_cleared : 0) + (($usdWallet ? (float)$usdWallet->usd_cleared : 0) * $fxRate);
    $totalEquity = $totalCashNgn + $ngxValue + $globalValueNgn + $cryptoValueNgn + $fixedIncomeValue;

    return [
      'success'                 => true,
      'trading_mode'            => 'demo',
      'wallet_balance'          => round($totalCashNgn, 2),
      'total_equity'            => round($totalEquity, 2),
      'holdings'                => $formattedHoldings,
      'ngx_value'               => round($ngxValue, 2),
      'fixed_income_value'      => round($fixedIncomeValue, 2),
      'global_stocks_value_usd' => round($globalValueNgn / $fxRate, 2),
      'global_stocks_value_ngn' => round($globalValueNgn, 2),
      'crypto_value_usd'        => round($cryptoValueNgn / $fxRate, 2),
      'crypto_value_ngn'        => round($cryptoValueNgn, 2),
      'portfolio_distribution'  => [
        ['label' => 'Wallet', 'value' => round($totalCashNgn, 2)],
        ['label' => 'NGX', 'value' => round($ngxValue, 2)],
        ['label' => 'Global Stocks', 'value' => round($globalValueNgn, 2)],
        ['label' => 'Crypto', 'value' => round($cryptoValueNgn, 2)],
        ['label' => 'Fixed Income', 'value' => round($fixedIncomeValue, 2)],
      ]
    ];
  }
}
