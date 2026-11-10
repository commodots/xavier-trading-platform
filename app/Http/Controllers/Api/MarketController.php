<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Symbol;
use App\Services\CSL\CslMarketDataProvider;
use App\Services\MarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function index(Request $request)
    {
        $query = Symbol::query();

        // Trending: return top 30 by volume across all markets
        if ($request->boolean('trending')) {
            $data = $query
                ->whereNotNull('last_price')
                ->orderByDesc('volume')
                ->limit(30)
                ->get(['symbol', 'name', 'last_price as price', 'change', 'volume', 'type', 'exchange as market'])
                ->map(fn ($item) => $this->formatSymbol($item));

            return response()->json(['success' => true, 'data' => $data]);
        }

        if ($request->filled('type')) {
            if ($request->type === 'crypto') {
                $query->where(function ($q) {
                    $q->where('type', 'crypto')->orWhere('exchange', 'CRYPTO');
                });
            } else {
                $query->where('type', $request->type);
            }
        }

        if ($request->filled('market')) {
            $market = strtoupper($request->market);

            if ($market === 'US') {
                $query->whereIn('exchange', ['NASDAQ', 'NYSE', 'US'])
                    ->where('type', '!=', 'crypto');
            } elseif ($market === 'UK') {
                $query->whereIn('exchange', ['LSE', 'LONDON', 'UK'])
                    ->where('type', '!=', 'crypto');
            } elseif ($market === 'NGX') {
                $query->whereIn('exchange', ['NGX', 'LOCAL', 'NIGERIA'])
                    ->where('type', '!=', 'crypto');
            } else {
                $query->where('exchange', $market);
            }
        }

        $data = $query
            ->whereNotNull('last_price')
            ->orderByDesc('volume')
            ->limit(100)
            ->get(['symbol', 'name', 'last_price as price', 'change', 'volume', 'type', 'exchange as market'])
            ->map(fn ($item) => $this->formatSymbol($item));

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function formatSymbol($item): array
    {
        $market = $this->normalizeMarket($item->market ?? $item->exchange ?? null, $item->type ?? null);
        $type = $this->resolveType($item->type ?? null, $market);

        $change = is_numeric($item->change) ? (float) $item->change : 0.0;

        return [
            'symbol' => $item->symbol,
            'name' => $item->name,
            'price' => (float) ($item->price ?? 0),
            'change' => $change,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'volume' => (float) ($item->volume ?? 0),
            'type' => $type,
            'market' => $market,
        ];
    }

    private function normalizeMarket(?string $market, ?string $type = null): string
    {
        $market = strtoupper(trim((string) ($market ?? '')));

        if (in_array($market, ['NASDAQ', 'NYSE', 'US', 'GLOBAL', 'INTERNATIONAL'], true)) {
            return 'US';
        }

        if (in_array($market, ['LSE', 'LONDON', 'UK'], true)) {
            return 'UK';
        }

        if (in_array($market, ['NGX', 'LOCAL', 'NIGERIA'], true)) {
            return 'NGX';
        }

        if ($market === 'CRYPTO' || strtolower($type) === 'crypto' || str_contains($market, 'USDT')) {
            return 'CRYPTO';
        }

        return $market ?: ($type === 'crypto' ? 'CRYPTO' : 'US');
    }

    private function resolveType(?string $type, ?string $market): string
    {
        $type = strtolower(trim((string) ($type ?? '')));
        if ($type === 'crypto' || strtoupper($market) === 'CRYPTO') {
            return 'crypto';
        }

        return 'stock';
    }

    public function ngx(): JsonResponse
    {
        $data = app(CslMarketDataProvider::class)->stockPrices();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function global()
    {
        $data = Symbol::where(function ($query) {
            $query->whereIn('exchange', ['NASDAQ', 'NYSE', 'US'])
                ->orWhere('type', 'global')
                ->orWhereNull('type');
        })
            ->where('symbol', 'not like', '%/USDT%')
            ->get(['symbol', 'name', 'last_price as price', 'change', 'volume', 'type', 'exchange'])
            ->map(function ($item) {
                return $this->formatSymbol($item);
            });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function quotes(Request $request)
    {
        $symbols = collect(explode(',', $request->query('symbols', '')))
            ->map(fn ($symbol) => strtoupper(trim($symbol)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $quotes = collect($symbols)
            ->map(fn ($symbol) => app(MarketService::class)->quoteDetails($symbol))
            ->filter(fn ($quote) => ! empty($quote['symbol']))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $quotes,
        ]);
    }

    public function crypto()
    {
        $data = cache()->remember('processed_crypto_prices', 300, function () {
            $prices = app(MarketService::class)->getPrices();
            $data = [];

            $coinNames = [
                'bitcoin' => ['symbol' => 'BTC', 'name' => 'Bitcoin'],
                'ethereum' => ['symbol' => 'ETH', 'name' => 'Ethereum'],
                'tether' => ['symbol' => 'USDT', 'name' => 'Tether'],
                'binancecoin' => ['symbol' => 'BNB', 'name' => 'Binance Coin'],
                'solana' => ['symbol' => 'SOL', 'name' => 'Solana'],
                'ripple' => ['symbol' => 'XRP', 'name' => 'Ripple'],
                'cardano' => ['symbol' => 'ADA', 'name' => 'Cardano'],
                'dogecoin' => ['symbol' => 'DOGE', 'name' => 'Dogecoin'],
                'polkadot' => ['symbol' => 'DOT', 'name' => 'Polkadot'],
                'tron' => ['symbol' => 'TRX', 'name' => 'TRON'],
                'chainlink' => ['symbol' => 'LINK', 'name' => 'Chainlink'],
                'matic-network' => ['symbol' => 'MATIC', 'name' => 'Polygon'],
            ];

            foreach ($prices as $id => $val) {
                if (isset($coinNames[$id])) {
                    $item = (object) [
                        'symbol' => $coinNames[$id]['symbol'],
                        'name' => $coinNames[$id]['name'],
                        'price' => is_array($val) ? ($val['usd'] ?? 0) : 0,
                        'change' => 0,
                        'volume' => 0,
                        'exchange' => 'CRYPTO',
                        'type' => 'crypto',
                    ];

                    $data[] = $this->formatSymbol($item);
                }
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getNGXInsights(): JsonResponse
    {
        $marketData = app(CslMarketDataProvider::class);
        $mostTraded = collect($marketData->stockPrices())
            ->sortByDesc('volume')
            ->take(5)
            ->map(fn (array $quote) => $this->formatNgxInsight($quote))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'gainers' => collect($marketData->topGainers())
                    ->take(5)
                    ->map(fn (array $quote) => $this->formatNgxInsight($quote))
                    ->values(),
                'losers' => collect($marketData->topLosers())
                    ->take(5)
                    ->map(fn (array $quote) => $this->formatNgxInsight($quote))
                    ->values(),
                'most_traded' => $mostTraded,
            ],
        ]);
    }

    public function ngxPrices(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => app(CslMarketDataProvider::class)->stockPrices(),
        ]);
    }

    public function ngxQuote(string $symbol): JsonResponse
    {
        $quote = app(CslMarketDataProvider::class)->quote(strtoupper($symbol));

        if (! is_numeric($quote['price']) || (float) $quote['price'] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'CSL did not return a valid quote for this symbol.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $quote,
        ]);
    }

    public function ngxOrderBook(string $symbol): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => app(CslMarketDataProvider::class)->orderBook(strtoupper($symbol)),
        ]);
    }

    public function ngxHistory(Request $request, string $symbol): JsonResponse
    {
        $request->merge([
            'range' => strtoupper($request->query('range', '1W')),
        ]);

        $validated = $request->validate([
            'range' => 'sometimes|in:1D,1W,1M,14D,30D,90D,1Y,ALL,CUSTOM',
            'start' => 'nullable|required_if:range,CUSTOM|date_format:Y-m-d',
            'end' => 'nullable|required_if:range,CUSTOM|date_format:Y-m-d|after_or_equal:start',
        ]);

        $rows = app(CslMarketDataProvider::class)->historical(
            strtoupper($symbol),
            strtolower($validated['range'] ?? '1w'),
            $validated['start'] ?? null,
            $validated['end'] ?? null
        );

        $history = collect($rows)
            ->map(fn (array $row) => [
                'date' => $row['date']
                    ?? $row['trade_date']
                    ?? $row['stock_date']
                    ?? $row['price_date']
                    ?? null,
                'close' => is_numeric(
                    $row['close']
                        ?? $row['closing_price']
                        ?? $row['close_price']
                        ?? $row['current_price']
                        ?? $row['stock_price']
                        ?? $row['price']
                        ?? null
                )
                    ? (float) (
                        $row['close']
                            ?? $row['closing_price']
                            ?? $row['close_price']
                            ?? $row['current_price']
                            ?? $row['stock_price']
                            ?? $row['price']
                    )
                    : null,
            ])
            ->filter(fn (array $row) => $row['date'] !== null && $row['close'] !== null)
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['data' => $history],
        ]);
    }

    private function formatNgxInsight(array $quote): array
    {
        return [
            'symbol' => $quote['symbol'],
            'name' => $quote['name'],
            'price' => (float) ($quote['price'] ?? 0),
            'change' => (float) ($quote['change_percent'] ?? $quote['change'] ?? 0),
            'volume' => (float) ($quote['volume'] ?? 0),
            'spark' => [],
            'market' => 'NGX',
        ];
    }

    public function getGlobalInsights()
    {
        $tabs = [
            'gainers' => ['AAPL', 'MSFT', 'NVDA'],
            'losers' => ['TSLA', 'NFLX', 'META'],
            'most_traded' => ['AAPL', 'AMZN', 'GOOGL'],
        ];

        $marketService = app(MarketService::class);
        $insights = [];

        foreach ($tabs as $tabKey => $symbols) {
            $insights[$tabKey] = collect($symbols)
                ->map(function ($symbol) use ($marketService) {
                    $quote = $marketService->quoteDetails($symbol, 'GLOBAL');

                    if (empty($quote) || empty($quote['symbol'])) {
                        return null;
                    }

                    return [
                        'symbol' => $quote['symbol'],
                        'name' => $quote['name'] ?? $symbol,
                        'price' => $quote['price'] ?? 0,
                        'change' => $quote['change'] ?? 0,
                        'volume' => $quote['volume'] ?? 0,
                        'spark' => $quote['spark'] ?? [],
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return response()->json(['success' => true, 'data' => $insights]);
    }
}
