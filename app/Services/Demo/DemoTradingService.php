<?php

namespace App\Services\Demo;

use App\Repositories\DemoWalletRepository;
use App\Repositories\DemoOrderRepository;
use App\Services\PriceService;
use App\Models\Demo\DemoWallet;
use App\Models\Demo\DemoOrder;
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

      return $order; 
    });
  }

  public function getPortfolio($userId)
  {
    $orders = DemoOrder::where('user_id', $userId)
      ->whereIn('status', ['filled', 'open', 'partially_filled'])
      ->orderBy('created_at', 'asc')
      ->get();

    $wallets = DemoWallet::where('user_id', $userId)->get();
    $fxRate = 1500;
    $holdings = [];

    foreach ($orders as $order) {
      $symbol = $order->symbol;
      $rawMarket = strtoupper($order->market);

      if (str_contains($rawMarket, 'CRYPTO')) $mType = 'crypto';
      elseif (str_contains($rawMarket, 'FIXED')) $mType = 'fixed_income';
      elseif (str_contains($rawMarket, 'GLOBAL') || str_contains($rawMarket, 'FOREIGN')) $mType = 'foreign';
      else $mType = 'local';

      if (!isset($holdings[$symbol])) {
        $holdings[$symbol] = [
          'quantity'      => 0,
          'total_cost'    => 0,
          'market'        => $mType,
          'current_price' => (float)$order->market_price,
        ];
      }

      $orderQty = (float)$order->quantity;
      $orderAmount = (float)$order->amount;

      if (strtolower($order->side) === 'buy') {
        $holdings[$symbol]['quantity']   += $orderQty;
        $holdings[$symbol]['total_cost'] += $orderAmount;
      } else {
        $currentQty = $holdings[$symbol]['quantity'];
        if ($currentQty > 0) {
          $avgCost = $holdings[$symbol]['total_cost'] / $currentQty;
          $holdings[$symbol]['total_cost'] -= ($orderQty * $avgCost);
        }
        $holdings[$symbol]['quantity'] -= $orderQty;
      }
      $holdings[$symbol]['current_price'] = (float)$order->market_price;
    }

    $ngxValue = 0;
    $globalValueNgn = 0;
    $cryptoValueNgn = 0;
    $fixedIncomeValue = 0;
    $formattedHoldings = [];

    foreach ($holdings as $symbol => $hData) {
      if ($hData['quantity'] < 0.00000001) continue;

      $qty = $hData['quantity'];
      $price = $hData['current_price'];
      $mType = $hData['market'];
      $isUsd = in_array($mType, ['foreign', 'crypto']);
      $multiplier = $isUsd ? $fxRate : 1;

      $avgPriceNgn = $qty > 0 ? ($hData['total_cost'] / $qty) : 0;
      $totalVal = $qty * $price;
      $valNgn = $totalVal * $multiplier;

      if ($mType === 'local') $ngxValue += $valNgn;
      elseif ($mType === 'foreign') $globalValueNgn += $valNgn;
      elseif ($mType === 'crypto') $cryptoValueNgn += $valNgn;
      elseif ($mType === 'fixed_income') $fixedIncomeValue += $valNgn;

      $formattedHoldings[] = [
        'symbol'             => $symbol,
        'name'               => $symbol,
        'quantity'           => round($qty, 8),
        'cleared_quantity'   => round($qty, 8),
        'uncleared_quantity' => 0,
        'avg_price'          => round($isUsd ? ($avgPriceNgn / $multiplier) : $avgPriceNgn, 2),
        'market_price'       => round($price, 2),
        'total_value'        => round($totalVal, 2),
        'total_value_ngn'    => round($valNgn, 2),
        'avg_price_ngn'      => round($avgPriceNgn, 2),
        'currency'           => $isUsd ? 'USD' : 'NGN',
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
