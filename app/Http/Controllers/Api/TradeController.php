<?php

namespace App\Http\Controllers\Api;

use App\Events\MarketUpdated;
use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Order;
use App\Models\Symbol;
use App\Models\SystemSetting;
use App\Models\Trade;
use App\Models\Wallet;
use App\Providers\AlpacaProvider;
use App\Services\MarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class TradeController extends Controller
{
    private function resolveModels($user)
    {
        $isDemo = $user->trading_mode === 'demo';

        return (object) [
            'isDemo' => $isDemo,
            'wallet' => $isDemo ? \App\Models\Demo\DemoWallet::class : Wallet::class,
            'transaction' => $isDemo ? \App\Models\Demo\DemoTransaction::class : NewTransaction::class,
            'order' => $isDemo ? \App\Models\Demo\DemoOrder::class : Order::class,
            'trade' => $isDemo ? \App\Models\Demo\DemoTrade::class : Trade::class,
        ];
    }

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

        $id = $map[strtoupper($symbol)] ?? null;

        if (! $id) {
            return 0;
        } // Don't guess. If it's not in the map, price is 0 (unavailable).

        return (float) ($prices[$id]['usd'] ?? 0);
    }

    public function updateMarket(Request $request)
    {
        if ($request->header('X-Finnhub-Secret') !== env('FINNHUB_SECRET')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $trades = $request->all();

            // Persist latest price to database
            foreach ($trades as $trade) {
                if (isset($trade['s']) && isset($trade['p'])) {
                    Symbol::where('symbol', $trade['s'])->update([
                        'last_price' => $trade['p'],
                        'volume' => $trade['v'] ?? 0,
                    ]);
                }
            }

            broadcast(new MarketUpdated($request->all()));

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateMarketData(Request $request)
    {
        $data = $request->all(); // The array of trades from Node

        broadcast(new MarketUpdated($data));

        return response()->json(['status' => 'broadcasted']);
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

        $settings = SystemSetting::first();
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

        // Fallback to real-time Finnhub price if CoinGecko mapping is missing
        if ($rawPrice <= 0) {
            $rawPrice = (float) Symbol::where('symbol', $symbol)->value('last_price');
        }

        if ($rawPrice <= 0) {
            return response()->json(['success' => false, 'message' => 'Price data unavailable for '.$symbol], 422);
        }

        $executionPrice = $marketService->applySpread($rawPrice, $request->type);
        $models = $this->resolveModels($user);

        return DB::transaction(function () use ($user, $amount, $executionPrice, $request, $symbol, $models) {
            $wallet = $models->wallet::where('user_id', $user->id)
                ->where('currency', 'USD')
                ->lockForUpdate()
                ->first();

            if (! $wallet || $wallet->usd_cleared < $amount) {
                throw new \Exception('Insufficient cleared funds.');
            }

            $order = $models->order::create([
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

            $trade = $models->trade::create([
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

            $models->transaction::create([
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
        $user = auth()->user();
        $models = $this->resolveModels($user);
        $trade = $models->trade::where('order_id', $id)
        ->where('user_id', $user->id)
        ->firstOrFail();

        if ($trade->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Trade is not open'], 422);
        }

        $symbol = strtoupper(explode('/', $trade->pair)[0]);

        // PERFORMANCE: Use local symbol price for faster execution
        $currentPrice = (float) Symbol::where('symbol', $symbol)->value('last_price');

        $marketService = app(MarketService::class);
        $currentPrice = $marketService->applySpread($currentPrice, 'sell');

        // P&L calculation: (Current - Entry) / Entry * Amount
        $profit = (($currentPrice - $trade->entry_price) / max($trade->entry_price, 0.00000001)) * $trade->amount;

        return DB::transaction(function () use ($trade, $currentPrice, $profit, $models) {
            $trade->update([
                'exit_price' => $currentPrice,
                'profit_loss' => $profit,
                'status' => 'closed',
            ]);

            $wallet = $models->wallet::where('user_id', $trade->user_id)
                ->where('currency', 'USD')
                ->lockForUpdate()
                ->first();

            if ($wallet) {
                // Return initial investment + profit (or minus loss)
                $wallet->increment('usd_cleared', $trade->amount + $profit);
                $wallet->increment('balance', $trade->amount + $profit);
            }

            $models->transaction::create([
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
    $user = $request->user();
    $models = $this->resolveModels($user);

    // Fetch OPEN TRADES (Positions), not just orders
    $positions = $models->order::where('user_id', $user->id)
        ->where('status', 'open')
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'data' => $positions,
    ]);
}

    public function searchSymbols(Request $request, $query = null)
    {
        $query = $query ?? trim($request->query('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = Symbol::query()
            ->where('symbol', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orderBy('symbol')
            ->limit(20)
            ->get(['symbol', 'name', 'exchange', 'type', 'last_price', 'volume', 'change']);

        return response()->json($results);
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'qty' => 'required|numeric|min:0.000001',
            'side' => 'required|in:buy,sell',
            'type' => 'required|in:market,limit,stop,bracket',
        ]);

        if ($request->type === 'limit' && ! $request->limit_price) {
            return response()->json(['message' => 'Limit price required for limit orders'], 422);
        }

        if ($request->type === 'bracket' && (! $request->take_profit || ! $request->stop_loss)) {
            return response()->json(['message' => 'Bracket orders require both take profit and stop loss'], 422);
        }

        $user = auth()->user();

        $currentPrice = (float) Symbol::where('symbol', $request->symbol)->value('last_price');
        $effectivePrice = (float) ($request->limit_price ?? $currentPrice);
        $totalAmount = (float) ($request->qty * $effectivePrice);

        // 1. Create local record
        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => $request->symbol,
            'quantity' => $request->qty,
            'side' => $request->side,
            'type' => $request->type,
            'status' => 'open',
            'amount' => $totalAmount,
            'market_price' => $effectivePrice,
            'limit_price' => $request->limit_price,
            'stop_price' => $request->stop_price,
            'take_profit' => $request->take_profit,
            'stop_loss' => $request->stop_loss,
            'currency' => 'USD',
            'market' => 'STOCKS',
        ]);

        // 2. Build Alpaca Payload
        $payload = [
            'symbol' => $request->symbol,
            'qty' => (float) $request->qty, // Changed to float to support fractional shares
            'side' => $request->side,
            'time_in_force' => 'gtc',
        ];

        switch ($request->type) {
            case 'market': $payload['type'] = 'market';
                break;
            case 'limit':
                $payload['type'] = 'limit';
                $payload['limit_price'] = (float) $request->limit_price;
                break;
            case 'stop':
                $payload['type'] = 'stop';
                $payload['stop_price'] = (float) $request->stop_price;
                break;
            case 'bracket':
                $payload['type'] = 'market';
                $payload['order_class'] = 'bracket';
                $payload['take_profit'] = ['limit_price' => (float) $request->take_profit];
                $payload['stop_loss'] = ['stop_price' => (float) $request->stop_loss];
                break;
        }

        // 3. Execute
        try {
            $alpaca = new AlpacaProvider;
            $response = $alpaca->placeAdvancedOrder($payload);

            $order->update([
                'alpaca_order_id' => $response['id'] ?? null,
            ]);

            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            $order->update(['status' => 'failed']);

            return response()->json(['message' => 'Alpaca Error: '.$e->getMessage()], 500);
        }
    }

    public function account()
    {
        $alpaca = new AlpacaProvider;

        return response()->json($alpaca->getAccount());
    }

    public function buy(Request $request)
    {
        $price = app(MarketService::class)->quote($request->symbol);

        // DEMO MODE
        if ($request->mode === 'demo') {
            // update wallet + ledger
            return response()->json(['status' => 'demo executed']);
        }

        // LIVE MODE (Alpaca)
        $alpaca = new AlpacaProvider;

        $order = Http::withHeaders([
            'APCA-API-KEY-ID' => env('ALPACA_API_KEY'),
            'APCA-API-SECRET-KEY' => env('ALPACA_SECRET_KEY'),
        ])->post(env('ALPACA_BASE_URL').'/v2/orders', [
            'symbol' => $request->symbol,
            'qty' => $request->qty,
            'side' => 'buy',
            'type' => 'market',
            'time_in_force' => 'gtc',
        ]);

        return $order->json();
    }

    public function sell(Request $request)
    {
        $price = app(MarketService::class)->quote($request->symbol);

        // DEMO MODE
        if ($request->mode === 'demo') {
            // update wallet + ledger
            return response()->json(['status' => 'demo executed']);
        }

        // LIVE MODE (Alpaca)
        $alpaca = new AlpacaProvider;

        $order = Http::withHeaders([
            'APCA-API-KEY-ID' => env('ALPACA_API_KEY'),
            'APCA-API-SECRET-KEY' => env('ALPACA_SECRET_KEY'),
        ])->post(env('ALPACA_BASE_URL').'/v2/orders', [
            'symbol' => $request->symbol,
            'qty' => $request->qty,
            'side' => 'sell',
            'type' => 'market',
            'time_in_force' => 'gtc',
        ]);

        return $order->json();
    }

    public function trackSymbol(Request $request)
    {
        // Handle both single symbol and array of symbols
        $symbols = $request->input('symbol') ? [$request->input('symbol')] : ($request->input('symbols') ?? []);
        $symbols = array_map('strtoupper', $symbols);

        // Update each symbol's last_seen timestamp in database
        foreach ($symbols as $symbol) {
            $existing = Symbol::where('symbol', $symbol)->first();

            if (! $existing) {
                Symbol::create(['symbol' => $symbol, 'last_price' => 0]);
            }

            // Notify the market-stream worker via Redis
            Redis::hset('active_tickers', $symbol, now()->timestamp);
            Redis::publish('symbol-updates', json_encode([
                'action' => 'subscribe',
                'symbol' => $symbol,
            ]));
        }

        return response()->json(['status' => 'tracking', 'symbols' => $symbols]);
    }
}
