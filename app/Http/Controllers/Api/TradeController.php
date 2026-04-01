<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Trade;
use App\Models\Wallet;
use App\Services\MarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeController extends Controller
{
    // ADD THIS HELPER METHOD to map symbols to CoinGecko IDs
    private function lookupPrice($symbol, $prices)
    {
        $map = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'USDT' => 'tether',
            'BNB' => 'binancecoin',
            'SOL' => 'solana',
            'XRP' => 'ripple',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'DOT' => 'polkadot',
            'TRX' => 'tron',
            'LINK' => 'chainlink',
            'MATIC' => 'matic-network',
        ];

        $id = $map[strtoupper($symbol)] ?? 'bitcoin';

        return (float) ($prices[$id]['usd'] ?? 0);
    }

    public function open(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'pair' => 'required|string',
            'type' => 'required|string|in:buy,sell',
        ]);

        $user = $request->user();
        $amount = (float) $request->input('amount');

        $settings = app(\App\Models\SystemSetting::class)::first();
        $maxTrade = (float) ($settings->max_trade_amount ?? 0);
        if ($maxTrade > 0 && $amount > $maxTrade) {
            return response()->json(['success' => false, 'message' => 'Trade exceeds max trade amount.'], 422);
        }

        $marketService = app(MarketService::class);
        $prices = $marketService->getPrices();

        // Extract symbol: "SOL" from "SOL/USDT"
        $symbol = strtoupper(explode('/', $request->input('pair'))[0]);

        // Use the helper to get the correct price
        $rawPrice = $this->lookupPrice($symbol, $prices);

        if ($rawPrice <= 0) {
            return response()->json(['success' => false, 'message' => 'Price data unavailable for '.$symbol], 422);
        }

        $executionPrice = $marketService->applySpread($rawPrice, $request->type);

        return DB::transaction(function () use ($user, $amount, $executionPrice, $request, $symbol) {
            $wallet = Wallet::where('user_id', $user->id)
                ->where('currency', 'USD')
                ->lockForUpdate()
                ->first();

            if (! $wallet || $wallet->usd_cleared < $amount) {
                throw new \Exception('Insufficient cleared funds.');
            }

            $order = \App\Models\Order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => $request->input('type'),
                'type' => 'market',
                'price' => $executionPrice,
                'quantity' => $amount, 
                'filled_quantity' => 0,
                'status' => 'filled',
                'currency' => 'USD',
                'market' => 'CRYPTO',
                'amount' => $amount,
                'market_price' => $executionPrice,
            ]);

            $trade = Trade::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'pair' => strtoupper($request->input('pair')),
                'type' => $request->input('type'),
                'amount' => $amount,
                'quantity' => $amount,
                'price' => $executionPrice,
                'entry_price' => $executionPrice,
                'status' => 'open',
            ]);

            $wallet->decrement('usd_cleared', $amount);

            NewTransaction::create([
                'user_id' => $user->id,
                'type' => 'buy_crypto',
                'amount' => $amount,
                'currency' => 'USD',
                'status' => 'completed',
                'meta' => ['pair' => $trade->pair, 'trade_id' => $trade->id],
            ]);

            return response()->json(['success' => true, 'data' => $trade]);
        });
    }

    public function close($id)
    {
        $trade = Trade::findOrFail($id);

        if ($trade->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Trade is not open'], 422);
        }

        // Dynamic price for closing too!
        $marketService = app(MarketService::class);
        $prices = $marketService->getPrices();
        $symbol = strtoupper(explode('/', $trade->pair)[0]);

        $currentPrice = $this->lookupPrice($symbol, $prices);
        $currentPrice = $marketService->applySpread($currentPrice, 'sell');

        // P&L calculation: (Current - Entry) / Entry * Amount
        $profit = (($currentPrice - $trade->entry_price) / max($trade->entry_price, 0.00000001)) * $trade->amount;

        return DB::transaction(function () use ($trade, $currentPrice, $profit) {
            $trade->update([
                'exit_price' => $currentPrice,
                'profit_loss' => $profit,
                'status' => 'closed',
            ]);

            $wallet = Wallet::where('user_id', $trade->user_id)
                ->where('currency', 'USD')
                ->lockForUpdate()
                ->first();

            if ($wallet) {
                // Return initial investment + profit (or minus loss)
                $wallet->increment('usd_cleared', $trade->amount + $profit);
                $wallet->increment('balance', $trade->amount + $profit);
            }

            NewTransaction::create([
                'user_id' => $trade->user_id,
                'type' => 'sell_crypto',
                'amount' => $trade->amount + $profit,
                'currency' => 'USD',
                'status' => 'completed',
                'meta' => ['pair' => $trade->pair, 'trade_id' => $trade->id, 'profit_loss' => $profit],
            ]);

            return response()->json(['success' => true, 'data' => $trade]);
        });
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => Trade::where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->get(),
        ]);
    }
}
