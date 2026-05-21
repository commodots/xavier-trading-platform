<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Symbol;
use App\Services\MarketService;

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
                // US stocks live under NASDAQ / NYSE exchanges
                $query->whereIn('exchange', ['NASDAQ', 'NYSE', 'US'])
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
        return [
            'symbol' => $item->symbol,
            'name'   => $item->name,
            'price'  => (float) ($item->price ?? 0),
            'change' => (float) ($item->change ?? 0),
            'volume' => (float) ($item->volume ?? 0),
            'type'   => $item->type,
            'market' => $item->market ?? $item->exchange,
        ];
    }

    public function ngx()
    {
        $data = Symbol::where('exchange', 'NGX')
            ->get(['symbol', 'name', 'last_price as price', 'change', 'volume'])
            ->map(function($item) {
                $item->price = (float) $item->price;
                return $item;
            });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function global()
    {
        $data = Symbol::where(function ($query) {
            $query->where('type', 'global')
                  ->orWhereIn('exchange', ['NASDAQ', 'NYSE']);
        })
        ->get(['symbol', 'name', 'last_price as price', 'change', 'volume'])
        ->map(function($item) {
            $item->price = (float) $item->price;
            $item->change = (float) ($item->change ?? 0);
            return $item;
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
                    $data[] = [
                        'symbol' => $coinNames[$id]['symbol'],
                        'name' => $coinNames[$id]['name'],
                        'price' => is_array($val) ? ($val['usd'] ?? 0) : 0,
                    ];
                }
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getNGXInsights()
    {
        $baseStocks = [
            ['symbol' => 'ZENITH', 'name' => 'Zenith Bank', 'price' => rand(4000, 6000) / 100],
            ['symbol' => 'GTCO', 'name' => 'GTCO Holdings', 'price' => rand(3000, 5500) / 100],
            ['symbol' => 'ACCESS', 'name' => 'Access Bank', 'price' => rand(3000, 5500) / 100],
            ['symbol' => 'MTNN', 'name' => 'MTN Nigeria', 'price' => rand(22000, 25000) / 100],
            ['symbol' => 'NB', 'name' => 'Nigerian Breweries', 'price' => rand(6500, 7500) / 100],
        ];

        return response()->json(['success' => true, 'data' => $this->processMockInsights($baseStocks)]);
    }

    public function getGlobalInsights()
    {
        $tabs = [
            'gainers'      => ['AAPL', 'MSFT', 'NVDA'],
            'losers'       => ['TSLA', 'NFLX', 'META'],
            'most_traded'  => ['AAPL', 'AMZN', 'GOOGL'],
        ];

        $marketService = app(MarketService::class);
        $insights = [];

        foreach ($tabs as $tabKey => $symbols) {
            $insights[$tabKey] = collect($symbols)
                ->map(function ($symbol) use ($marketService) {
                    $quote = $marketService->quoteDetails($symbol);

                    if (empty($quote) || empty($quote['symbol'])) {
                        return null;
                    }

                    return [
                        'symbol' => $quote['symbol'],
                        'name'   => $quote['name'] ?? $symbol,
                        'price'  => $quote['price'] ?? 0,
                        'change' => $quote['change'] ?? 0,
                        'volume' => $quote['volume'] ?? 0,
                        'spark'  => $quote['spark'] ?? [] 
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return response()->json(['success' => true, 'data' => $insights]);
    }

    private function processMockInsights(array $stocks)
    {
        $hydrated = collect($stocks)->map(function ($stock) {
            $change = rand(-500, 500) / 100; 
            $currentPrice = $stock['price'];
            
            $spark = [
                $currentPrice * (1 - ($change * 0.008)),
                $currentPrice * (1 - ($change * 0.006)),
                $currentPrice * (1 - ($change * 0.004)),
                $currentPrice * (1 - ($change * 0.002)),
                $currentPrice
            ];

            return [
                'symbol' => $stock['symbol'],
                'name'   => $stock['name'],
                'price'  => $currentPrice,
                'change' => $change,
                'volume' => rand(100000, 2500000),
                'spark'  => $spark
            ];
        });

        return [
            'gainers'      => $hydrated->sortByDesc('change')->values()->all(),
            'losers'       => $hydrated->sortBy('change')->values()->all(),
            'most_traded'  => $hydrated->sortByDesc('volume')->values()->all(),
            'least_traded' => $hydrated->sortBy('volume')->values()->all(),
        ];
    }
}