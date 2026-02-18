<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoWalletService;
use App\Services\Demo\DemoTradingService;
use Illuminate\Http\Request;
use App\Models\DemoOrder;
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

    return response()->json(
      $this->walletService->fund($request->user()->id, $request->amount)
    );
  }

  /** Place a simulated trade in demo mode */
  public function placeTrade(Request $request)
  {
    $request->validate([
      'symbol' => 'required|string',
      'market_type' => 'required|in:local,international,crypto',
      'type' => 'required|in:buy,sell',
      'quantity' => 'required|numeric|min:0'
    ]);

    $order = $this->tradingService->executeTrade(
      $request->user(),
      $request->symbol,
      $request->market_type,
      $request->type,
      $request->quantity
    );

    return response()->json($order);
  }

  /** Fetch user portfolio in demo mode */
  public function portfolio(Request $request)
  {
    $portfolioData = $this->tradingService->getPortfolio($request->user()->id);

    return response()->json([
      'success' => true,
      'data' => $portfolioData
    ]);
  }

  /** Reset demo account (wallet + orders) */
  public function resetDemo(Request $request)
  {
    $this->walletService->reset($request->user()->id);

    return response()->json(['message' => 'Demo reset successful']);
  }

  /** Fetch user transactions/orders in demo mode */
  public function transactions(Request $request)
  {

    $orders = $this->tradingService->getPortfolio($request->user()->id);

    $demoOrders = DemoOrder::where('user_id', $request->user()->id)
      ->orderBy('created_at', 'desc')
      ->limit(10)
      ->get();

    return response()->json([
      'success' => true,
      'data' => $demoOrders
    ]);
  }
}
