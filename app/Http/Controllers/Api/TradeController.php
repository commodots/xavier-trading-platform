<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewTransaction;
use App\Models\Order;
use App\Models\Symbol;
use App\Models\SystemSetting;
use App\Models\Trade;
use App\Models\Wallet;
use App\Providers\AlpacaProvider;
use App\Services\MarketService;
use App\Models\Ledger;
use App\Notifications\TradeExecutedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;


class TradeController extends Controller
{
    private function resolveModels($user): object
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
    private function lookupPrice($symbol, $prices): float
    {
        return app(MarketService::class)->lookupCryptoPrice($symbol, $prices);
    }

    private function matchesSymbolSearch(Symbol $symbol, string $query): bool
    {
        $normalizedQuery = strtolower(trim($query));
        $normalizedSymbol = strtolower($symbol->symbol);
        $normalizedName = strtolower($symbol->name ?? '');

        if (str_contains($normalizedSymbol, $normalizedQuery) || str_contains($normalizedName, $normalizedQuery)) {
            return true;
        }

        return levenshtein($normalizedSymbol, $normalizedQuery) <= 1;
    }

    public function updateMarket(Request $request): JsonResponse
    {
        $secret = config('services.finnhub.secret');

        if ($request->header('X-Finnhub-Secret') !== $secret) {
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

            broadcast(new \App\Events\MarketUpdated($trades))->toOthers();

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function open(Request $request): JsonResponse
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

            $walletBefore = $wallet ? $wallet->usd_cleared : 0;

            if (! $wallet || $wallet->usd_cleared < $amount) {
                throw new \Exception('Insufficient cleared funds.');
            }

            $quantity = $amount / $executionPrice;

            Log::info('Crypto Trade Opening', [
                'user_id' => $user->id,
                'pair' => $request->input('pair'),
                'amount_usd' => $amount,
                'entry_price' => $executionPrice,
                'calculated_quantity' => $quantity,
                'wallet_before_cleared' => $walletBefore,
                'wallet_before_total' => $wallet->balance,
                'mode' => $user->trading_mode,
            ]);

            $order = $models->order::create([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => $request->input('type'),
                'type' => 'market',
                'price' => $executionPrice,
                'quantity' => $quantity,
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
                'quantity' => $quantity,
                'price' => $executionPrice,
                'entry_price' => $executionPrice,
                'status' => 'open',
            ]);

            $wallet->decrement('usd_cleared', $amount);
            $wallet->increment('locked', $amount); // Standardize: Move to locked instead of removing
            $wallet->refresh(); // Refresh to get latest values before calculating balance
            $wallet->balance = $wallet->usd_cleared + $wallet->usd_uncleared + $wallet->locked;
            $wallet->save();

            $models->transaction::create([
                'user_id' => $user->id,
                'type' => 'buy_crypto',
                'amount' => $amount,
                'currency' => 'USD',
                'status' => 'completed',
                'meta' => ['pair' => $trade->pair, 'trade_id' => $trade->id],
            ]);

            $user->notify(new TradeExecutedNotification($trade, 'open'));

            return response()->json(['success' => true, 'data' => $trade]);
        });
    }

    public function close($id): JsonResponse
    {
        $user = auth()->user();
        $models = $this->resolveModels($user);

        $trade = $models->trade::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        //  Only open trades can be closed
        if ($trade->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Trade is not open'], 422);
        }

        if (! $trade->quantity || ! $trade->entry_price || ! $trade->amount) {
            return response()->json(['success' => false, 'message' => 'Invalid trade data'], 422);
        }

        $symbol = strtoupper(explode('/', $trade->pair)[0]);

        $marketService = app(MarketService::class);
        $prices = null;

        // LIVE API: Fetch real-time quote
        if ($marketService->isCryptoPair($trade->pair)) {
            // For crypto, use CoinGecko prices
            $prices = $marketService->getPrices();
            $currentPrice = $this->lookupPrice($symbol, $prices);
            if ($currentPrice <= 0) {
                $currentPrice = (float) Symbol::where('symbol', $symbol)->value('last_price');
            }
        } else {
            // For stocks, use Finnhub
            $currentPrice = $marketService->quote($symbol);
        }

        Log::info('TradeController::close current price debug', [
            'trade_id' => $trade->id,
            'pair' => $trade->pair,
            'symbol' => $symbol,
            'prices' => $prices ?? null,
            'current_price' => $currentPrice,
        ]);

        if ($currentPrice <= 0) {
            return response()->json(['success' => false, 'message' => "Price unavailable for {$symbol}"], 422);
        }

        $currentPrice = $marketService->applySpread($currentPrice, 'sell');

        $quantity = (float) $trade->quantity;
        $buyPrice = (float) $trade->entry_price;
        $sellPrice = (float) $currentPrice;

        $buyValue = $quantity * $buyPrice;     // Initial investment (should match $trade->amount)
        $sellValue = $quantity * $sellPrice;   // Total proceeds from selling

        // P&L: sell_value - buy_value (not percentage * amount)
        $profitLoss = $sellValue - $buyValue;

        Log::info('Crypto Trade Closing - Calculation', [
            'trade_id' => $id,
            'user_id' => $user->id,
            'pair' => $trade->pair,
            'quantity' => $quantity,
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'buy_value' => $buyValue,
            'sell_value' => $sellValue,
            'profit_loss' => $profitLoss,
            'trade_amount' => $trade->amount,
        ]);

        try {
            return DB::transaction(function () use ($id, $user, $sellPrice, $profitLoss, $models, $buyValue, $sellValue, $quantity, $buyPrice) {
                // Refetch inside transaction with lock to prevent race conditions
                $lockedTrade = $models->trade::where('id', $id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedTrade) {
                    throw new \Exception('Trade not found');
                }

                // Double-check status inside transaction
                if ($lockedTrade->status !== 'open') {
                    throw new \Exception('Trade already closed');
                }

                // Update trade record with close price and P&L
                $lockedTrade->update([
                    'exit_price' => $sellPrice,
                    'profit_loss' => $profitLoss,
                    'status' => 'closed',
                    'settlement_status' => 'pending',
                    'settlement_date' => now()->addDay()->startOfDay(),
                ]);

                // Lock wallet to prevent concurrent updates
                $wallet = $models->wallet::where('user_id', $user->id)
                    ->where('currency', 'USD')
                    ->lockForUpdate()
                    ->firstOrFail();

                $walletBeforeLocked = (float) $wallet->locked;
                $walletBeforeCleared = (float) $wallet->usd_cleared;

                // WALLET RULE (T+1): Return capital from locked + add FULL proceeds to UNCLEARED
                // This prevents immediate withdrawal until the settlement job runs.
                $wallet->decrement('locked', $buyValue);        // Release locked capital
                $wallet->increment('usd_uncleared', $sellValue); // Add FULL proceeds to uncleared
                $wallet->refresh(); // Refresh to get latest values after decrement/increment
                $wallet->balance = $wallet->usd_cleared + $wallet->usd_uncleared + $wallet->locked;
                $wallet->save();

                $walletAfterLocked = (float) $wallet->locked;
                $walletAfterCleared = (float) $wallet->usd_cleared;

                Log::info('Crypto Trade Closing - Wallet Updated', [
                    'trade_id' => $id,
                    'locked_before' => $walletBeforeLocked,
                    'locked_after' => $walletAfterLocked,
                    'cleared_before' => $walletBeforeCleared,
                    'cleared_after' => $walletAfterCleared,
                    'capital_returned' => $buyValue,
                    'proceeds_added' => $sellValue,
                    'profit_recorded' => $profitLoss,
                ]);

                // Create ledger entry for wallet update
                Ledger::create([
                    'user_id' => $user->id,
                    'currency' => 'USD',
                    'amount' => $profitLoss,
                    'type' => 'crypto_trade_sell',
                    'status' => 'completed',
                    'reference' => "Trade #{$lockedTrade->id}",
                    'meta' => [
                        'trade_id' => $lockedTrade->id,
                        'pair' => $lockedTrade->pair,
                        'quantity' => $quantity,
                        'entry_price' => $buyPrice,
                        'exit_price' => $sellPrice,
                        'buy_value' => $buyValue,
                        'sell_value' => $sellValue,
                    ],
                ]);

                // ✅ Create transaction record for audit trail
                $models->transaction::create([
                    'user_id' => $user->id,
                    'type' => 'sell_crypto',
                    'amount' => $sellValue,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'meta' => [
                        'pair' => $lockedTrade->pair,
                        'trade_id' => $lockedTrade->id,
                        'quantity' => $quantity,
                        'price' => $sellPrice,
                        'buy_value' => $buyValue,
                        'sell_value' => $sellValue,
                        'profit_loss' => $profitLoss,
                    ],
                ]);

                Log::info('Crypto Trade Closed Successfully', [
                    'trade_id' => $id,
                    'user_id' => $user->id,
                    'status' => 'success',
                ]);

                $user->notify(new TradeExecutedNotification($lockedTrade, 'close'));

                return response()->json(['success' => true, 'data' => $lockedTrade]);
            });
        } catch (\Exception $e) {
            Log::error('Crypto Trade Close Failed', [
                'trade_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $models = $this->resolveModels($user);

            $marketService = app(MarketService::class);
            $prices = $marketService->getPrices();

            $trades = $models->trade::where('user_id', $user->id)
                ->where('status', 'open')
                ->whereHas('order', fn ($query) => $query->whereIn('market', ['GLOBAL', 'CRYPTO', 'NGX', 'LOCAL']))
                ->latest()
                ->get();

            $orders = $models->order::where('user_id', $user->id)
                ->whereIn('market', ['GLOBAL', 'CRYPTO', 'NGX', 'LOCAL'])
                ->whereIn('status', ['filled', 'open', 'partially_filled'])
                ->latest()
                ->get();

            $stockSymbols = collect()
                ->merge($trades->map(fn ($trade) => strtoupper(explode('/', $trade->pair)[0])))
                ->merge($orders->map(fn ($order) => strtoupper($order->symbol)))
                ->filter()
                ->unique()
                ->values();

            $stockQuotes = Symbol::whereIn('symbol', $stockSymbols)
                ->pluck('last_price', 'symbol')
                ->toArray();

            $categoryFilter = strtoupper($request->query('category', 'ALL'));
            $validCategories = ['ALL', 'CRYPTO', 'GLOBAL'];
            if (! in_array($categoryFilter, $validCategories, true)) {
                $categoryFilter = 'ALL';
            }

            $tradePositions = $trades->map(function ($t) use ($prices, $stockQuotes, $marketService) {
                $symbol = strtoupper(explode('/', $t->pair)[0]);
                $entryPrice = (float) $t->entry_price;
                $marketPrice = $entryPrice;

                $isCrypto = $marketService->isCryptoPair($t->pair);
                $isLocal = str_contains($t->pair, '/NGN') || (isset($t->order) && in_array($t->order->market, ['NGX', 'LOCAL']));
                $category = $isCrypto ? 'CRYPTO' : ($isLocal ? 'NGX' : 'GLOBAL');

                if ($isCrypto) {
                    $marketPrice = $this->lookupPrice($symbol, $prices);
                    if ($marketPrice <= 0) {
                        $marketPrice = (float) Symbol::where('symbol', $symbol)->value('last_price') ?: $entryPrice;
                    }
                } else {
                    $stockPrice = (float) ($stockQuotes[$symbol] ?? 0);
                    if ($stockPrice > 0) {
                        $marketPrice = $stockPrice;
                    }
                }

                $priceDiff = $t->type === 'buy' ? $marketPrice - $entryPrice : $entryPrice - $marketPrice;
                $unrealizedPlPercent = $entryPrice > 0 ? ($priceDiff / $entryPrice) * 100 : 0;
                $unrealizedPl = $priceDiff * (float) $t->amount;

                return [
                    'id' => $t->id,
                    'position_type' => 'trade',
                    'symbol' => $t->pair,
                    'pair' => $t->pair,
                    'side' => $t->type,
                    'quantity' => (float) $t->quantity,
                    'entry_price' => $entryPrice,
                    'market_price' => $marketPrice,
                    'amount' => (float) $t->amount,
                    'status' => $t->status,
                    'type' => $t->type,
                    'currency' => 'USD',
                    'category' => $category,
                    'unrealized_pl' => $unrealizedPl,
                    'unrealized_pl_percent' => $unrealizedPlPercent,
                ];
            });

            $orderPositions = $orders->map(function ($order) use ($prices, $stockQuotes, $marketService) {
                $symbol = strtoupper($order->symbol);
                $entryPrice = (float) $order->market_price;
                $marketPrice = $entryPrice;

                $isCrypto = $marketService->isCryptoPair($order->symbol) || $order->market === 'CRYPTO';
                $isLocal = in_array($order->market, ['NGX', 'LOCAL']);
                $category = $isCrypto ? 'CRYPTO' : ($isLocal ? 'NGX' : 'GLOBAL');

                if ($isCrypto) {
                    $cryptoPrice = $this->lookupPrice($symbol, $prices);
                    if ($cryptoPrice > 0) {
                        $marketPrice = $cryptoPrice;
                    } elseif ($marketPrice <= 0) {
                        $marketPrice = (float) Symbol::where('symbol', $symbol)->value('last_price') ?: $entryPrice;
                    }
                } else {
                    $stockPrice = (float) ($stockQuotes[$symbol] ?? 0);
                    if ($stockPrice > 0) {
                        $marketPrice = $stockPrice;
                    }
                }

                $priceDiff = $order->side === 'buy' ? $marketPrice - $entryPrice : $entryPrice - $marketPrice;
                $unrealizedPlPercent = $entryPrice > 0 ? ($priceDiff / $entryPrice) * 100 : 0;

                return [
                    'id' => $order->id,
                    'position_type' => 'order',
                    'symbol' => $order->symbol,
                    'pair' => $isCrypto ? strtoupper($order->symbol).'/USDT' : null,
                    'side' => $order->side,
                    'quantity' => (float) $order->quantity,
                    'entry_price' => $entryPrice,
                    'market_price' => $marketPrice,
                    'amount' => (float) $order->amount,
                    'status' => $order->status,
                    'type' => $order->type,
                    'currency' => $order->currency,
                    'category' => $category,
                    'unrealized_pl_percent' => $unrealizedPlPercent,
                ];
            });

            $positions = $tradePositions->concat($orderPositions);

            if ($categoryFilter !== 'ALL') {
                $positions = $positions->filter(fn ($pos) => ($pos['category'] ?? '') === $categoryFilter);
            }

            return response()->json([
                'success' => true,
                'data' => $positions->values(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Trade positions error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load positions at this time.',
            ], 500);
        }
    }

    public function searchSymbols(Request $request, $query = null): JsonResponse
    {
        $query = $query ?? trim($request->query('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = Symbol::query();

        // Check if we are running in a test/SQLite environment
        if (config('database.default') === 'sqlite') {
            $results->where(function ($q) use ($query) {
                $q->where('symbol', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            });
        } else {
            // Use high-performance Fulltext search for MySQL (Production)
            $results->where(function ($q) use ($query) {
                $q->whereFulltext('name', $query)
                    ->orWhere('symbol', 'like', "%{$query}%");
            });
        }

        $finalResults = $results->orderBy('symbol')
            ->limit(20)
            ->get(['symbol', 'name', 'exchange', 'type', 'last_price', 'volume', 'change']);

        if ($finalResults->count() < 20) {
            $fallback = Symbol::select(['symbol', 'name', 'exchange', 'type', 'last_price', 'volume', 'change'])
                ->get()
                ->filter(fn ($symbol) => $this->matchesSymbolSearch($symbol, $query));

            foreach ($fallback as $symbol) {
                if ($finalResults->contains('symbol', $symbol->symbol)) {
                    continue;
                }

                $finalResults->push($symbol);

                if ($finalResults->count() >= 20) {
                    break;
                }
            }
        }

        return response()->json($finalResults->take(20));
    }

    public function placeOrder(Request $request): JsonResponse
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
        $models = $this->resolveModels($user);

        $marketService = app(MarketService::class);

        $currentPrice = $marketService->quote($request->symbol);

        if ($currentPrice <= 0) {
            return response()->json(['message' => "Price for {$request->symbol} is currently unavailable. Please try again in a moment."], 422);
        }

        // Logic: If it's a limit order, use the limit price for calculations.
        // Otherwise (Market, Stop, Bracket), use the current market price.
        $effectivePrice = ($request->type === 'limit')
            ? (float) $request->limit_price
            : $currentPrice;

        $totalAmount = (float) ($request->qty * $effectivePrice);

        $settings = SystemSetting::first();
        $maxTrade = (float) ($settings->max_trade_amount ?? 0);
        if ($maxTrade > 0 && $totalAmount > $maxTrade) {
            return response()->json(['success' => false, 'message' => 'Order value exceeds max trade amount.'], 422);
        }

        try {
            return DB::transaction(function () use ($request, $user, $totalAmount, $currentPrice, $models) {
                // Balance Check and Deduction (For Buy Orders)
                if ($request->side === 'buy') {
                    $wallet = $models->wallet::where('user_id', $user->id)
                        ->where('currency', 'USD')
                        ->lockForUpdate()
                        ->first();

                    if (! $wallet || $wallet->usd_cleared < $totalAmount) {
                        throw new \Exception('Insufficient cleared USD balance to place this order.');
                    }

                    // Standardize: Move to locked state instead of decrementing total balance
                    $wallet->decrement('usd_cleared', $totalAmount);
                    $wallet->increment('locked', $totalAmount);

                    $wallet->refresh();
                    $wallet->balance = $wallet->usd_cleared + $wallet->usd_uncleared + $wallet->locked;
                    $wallet->save();
                }

                $order = $models->order::create([
                    'user_id' => $user->id,
                    'symbol' => strtoupper($request->symbol),
                    'quantity' => $request->qty,
                    'side' => $request->side,
                    'type' => $request->type,
                    'status' => 'open',
                    'amount' => $totalAmount,
                    'market_price' => $currentPrice,
                    'limit_price' => $request->limit_price,
                    'stop_price' => $request->stop_price,
                    'take_profit' => $request->take_profit,
                    'stop_loss' => $request->stop_loss,
                    'currency' => 'USD',
                    'market' => 'GLOBAL',
                ]);

                // 2. Build Alpaca Payload
                $payload = [
                    'symbol' => $request->symbol,
                    'qty' => (float) $request->qty, // Changed to float to support fractional shares
                    'side' => $request->side,
                    'time_in_force' => 'gtc',
                ];

                switch ($request->type) {
                    case 'market':
                        $payload['type'] = 'market';
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

                    $models->transaction::create([
                        'user_id' => $user->id,
                        'type' => $request->side === 'buy' ? 'buy_stock' : 'sell_stock',
                        'amount' => $totalAmount,
                        'net_amount' => $totalAmount,
                        'currency' => 'USD',
                        'status' => 'completed',
                        'meta' => [
                            'order_id' => $order->id,
                            'symbol' => strtoupper($request->symbol),
                            'quantity' => $request->qty,
                            'alpaca_id' => $response['id'] ?? null,
                            'order_type' => $request->type,
                        ],
                    ]);

                    return response()->json(['success' => true, 'data' => $order]);
                } catch (\Exception $e) {
                    throw $e;
                }
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order Failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function account(): JsonResponse
    {
        $alpaca = new AlpacaProvider;

        return response()->json($alpaca->getAccount());
    }

    public function buy(Request $request): JsonResponse
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
            'APCA-API-KEY-ID' => config('services.alpaca.api_key'),
            'APCA-API-SECRET-KEY' => config('services.alpaca.secret_key'),
        ])->post(config('services.alpaca.base_url').'/v2/orders', [
            'symbol' => $request->symbol,
            'qty' => $request->qty,
            'side' => 'buy',
            'type' => 'market',
            'time_in_force' => 'gtc',
        ]);

        return $order->json();
    }

    public function sell(Request $request): JsonResponse
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
            'APCA-API-KEY-ID' => config('services.alpaca.api_key'),
            'APCA-API-SECRET-KEY' => config('services.alpaca.secret_key'),
        ])->post(config('services.alpaca.base_url').'/v2/orders', [
            'symbol' => $request->symbol,
            'qty' => $request->qty,
            'side' => 'sell',
            'type' => 'market',
            'time_in_force' => 'gtc',
        ]);

        return $order->json();
    }

    public function trackSymbol(Request $request): JsonResponse
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

   /**
     * Fetch market insights (gainers, losers, most active) for a specific market type.
     */
    public function insights(string $market): JsonResponse
    {
        try {
            $normalizedMarket = strtoupper(trim($market));
            
            // Cache the results for 2 minutes to prevent heavy DB strain 
            // from rapid dashboard tab switching or concurrent users.
            $data = Cache::remember("market_insights_{$normalizedMarket}", 120, function () use ($normalizedMarket) {
                $baseQuery = Symbol::query();

                // Align market types with the categories used in index()
                if ($normalizedMarket === 'NGX' || $normalizedMarket === 'LOCAL') {
                    $baseQuery->where(function ($q) {
                        $q->where('exchange', 'NGX')
                          ->orWhere('exchange', 'local')
                          ->orWhere('type', 'local')
                          ->orWhere('symbol', 'like', '%.NG%');
                    });
                } elseif ($normalizedMarket === 'CRYPTO') {
                    $baseQuery->where(function ($q) {
                        $q->where('type', 'crypto')
                          ->orWhere('symbol', 'like', '%/USDT%');
                    });
                } else {
                    // Default to Global Equities (NASDAQ/NYSE/etc)
                    $baseQuery->where(function ($q) {
                        $q->whereNotIn('exchange', ['NGX', 'local'])
                          ->whereNotIn('type', ['crypto', 'local'])
                          ->orWhereNull('type');
                    })->where('symbol', 'not like', '%/USDT%');
                }

                $mapData = fn($s) => [
                    'symbol' => $s->symbol,
                    'name'   => $s->name,
                    'price'  => (float) $s->last_price,
                    'change' => (float) ($s->change ?? 0),
                ];

                return [
                    'gainers'      => (clone $baseQuery)->where('change', '>', 0)->orderByDesc('change')->limit(5)->get()->map($mapData),
                    'losers'       => (clone $baseQuery)->where('change', '<', 0)->orderBy('change')->limit(5)->get()->map($mapData),
                    'most_traded'  => (clone $baseQuery)->orderByDesc('volume')->limit(5)->get()->map($mapData),
                    'least_traded' => (clone $baseQuery)->orderBy('volume')->limit(5)->get()->map($mapData),
                ];
            });

            return response()->json(['success' => true, 'data' => $data], 200);

        } catch (\Throwable $e) {
            Log::error('Market insights retrieval failed: ' . $e->getMessage(), [
                'market'    => $market,
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load market insights at this time.'
            ], 500);
        }
    }
}
