<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoWalletService;
use App\Services\Demo\DemoTradingService;
use Illuminate\Http\Request;
use App\Models\Demo\DemoOrder;
use Illuminate\Support\Facades\DB;

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

    public function startDemo(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:1000']);
        return response()->json($this->walletService->fund($request->user()->id, $request->amount));
    }

    public function placeTrade(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'market' => 'required|string|in:NGX,GLOBAL,CRYPTO,FIXED_INCOME,LOCAL,INTERNATIONAL',           
            'side' => 'required|in:buy,sell',
            'amount' => 'required|numeric|min:0',
            'market_price' => 'required|numeric'
        ]);

        return DB::transaction(function () use ($request) {

            $tradeData = [
                "symbol" => $request->symbol,
                "market" => $request->market,
                "side" => $request->side,
                "amount" => $request->amount,
                "market_price" => $request->market_price,
            ];

            // 1. Let the service handle the complex trading logic
            $order = $this->tradingService->executeTrade(
                $request->user(),
                $tradeData
            );

            // 2. Safety Refund check! If it failed, give the money back immediately.
            if (in_array($order->status, ['failed', 'canceled', 'cancelled']) && $request->side === 'buy') {
                $wallet = DB::table('demo_wallets')->where('user_id', $request->user()->id)->first();
                if ($wallet) {
                    DB::table('demo_wallets')
                        ->where('user_id', $request->user()->id)
                        ->increment('balance', $request->amount);
                }
            }

            return response()->json($order);
        });
    }

    public function portfolio(Request $request)
    {
        // 🌟 Reverted to the clean 1-liner! 
        // The newly updated DemoTradingService now formats this perfectly for Vue.
        $portfolioData = $this->tradingService->getPortfolio($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $portfolioData
        ]);
    }

    public function resetDemo(Request $request)
    {
        $this->walletService->reset($request->user()->id);
        return response()->json(['message' => 'Demo reset successful']);
    }

    public function transactions(Request $request)
    {
        $demoOrders = DemoOrder::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['success' => true, 'data' => $demoOrders]);
    }
}
