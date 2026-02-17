<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoWalletService;
use App\Services\Demo\DemoTradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoController extends Controller
{
  protected $walletService;
  protected $tradingService;

  public function __construct(
    DemoWalletService $walletService,
    DemoTradingService $tradingService
  ) {
    $this->walletService = $walletService;
    $this->tradingService = $tradingService;
  }

  /** Switch user between live and demo mode */
  public function switchMode(Request $request)
  {
    $request->validate(['mode' => 'required|in:live,demo']);
    $user = $request->user();
    $user->trading_mode = $request->mode;
    $user->save();

    return response()->json([
      'message' => 'Trading mode switched successfully',
      'mode' => $user->trading_mode
    ]);
  }

  /** Start or fund demo wallet with a trial amount */
  public function startDemo(Request $request)
  {
    $request->validate(['amount' => 'required|numeric|min:1000']);

    $wallet = $this->walletService->fund($request->user()->id, $request->amount);

    return response()->json([
      'success' => true,
      'wallet' => $wallet
    ]);
  }

  /** Place a simulated trade in demo mode */
  public function placeTrade(Request $request)
  {
    $request->validate([
      'symbol' => 'required|string',
      'market_type' => 'required|in:local,international,crypto',
      'type' => 'required|in:buy,sell',
      'quantity' => 'required|integer|min:1'
    ]);

    try {
      $order = $this->tradingService->executeTrade(
        $request->user(),
        $request->symbol,
        $request->market_type,
        $request->type,
        $request->quantity
      );
      return response()->json([
        'success' => true,
        'data' => $order
      ]);
    } catch (\Exception $e) {
      Log::error('Demo trade failed', ['error' => $e->getMessage()]);
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 400);
    }
  }

  /** Fetch user portfolio in demo mode */
  public function portfolio(Request $request)
    {
        $userId = $request->user()->id;
        
        $walletRepo = app(\App\Repositories\DemoWalletRepository::class);
        $wallet = $walletRepo->findByUser($userId);
        
        $holdings = $this->tradingService->getPortfolio($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $wallet ? $wallet->balance : 0,
                'equity' => $wallet ? $wallet->equity : 0,
                'holdings' => $holdings
            ]
        ]);
    }

  /** Reset demo account (wallet + orders) */
  public function resetDemo(Request $request)
    {
        $userId = $request->user()->id;
        $this->walletService->reset($userId);
        app(\App\Repositories\DemoOrderRepository::class)->deleteUserOrders($userId);

        return response()->json([
            'success' => true,
            'message' => 'Demo account reset successfully'
        ]);
    }
}
