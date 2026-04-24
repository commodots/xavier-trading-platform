<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Demo\DemoPortfolio;
use App\Models\Portfolio;
use App\Services\Demo\DemoTradingService;
use App\Services\LiveTradingService;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    private function resolveModels($user, $request = null)
    {
        $requestedMode = $request ? $request->query('mode', $user->trading_mode) : $user->trading_mode;
        $isDemo = $requestedMode === 'demo';

        return (object) [
            'portfolio' => $isDemo ? new DemoPortfolio : new Portfolio,
            'isDemo' => $isDemo,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $models = $this->resolveModels($user, $request);

        // Route to the appropriate service provider
        $service = $models->isDemo
                    ? app(DemoTradingService::class)
                    : app(LiveTradingService::class);

        $portfolio = $service->getPortfolio($user->id);

        return response()->json([
            'success' => true,
            'mode' => $models->isDemo ? 'demo' : 'live',
            'data' => $portfolio,
        ]);
    }

    public function trading(Request $request)
    {
        $user = $request->user();
        $models = $this->resolveModels($user, $request);

        if (! $models->isDemo) {
            // Query local positions attributed to this user
            $positions = \App\Models\Position::where('user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'mode' => 'live',
                'positions' => $positions,
                'account' => $user->wallet, // Use local wallet for balance
            ]);
        }

        return response()->json([
            'success' => true,
            'mode' => 'demo',
            'positions' => [],
            'account' => null,
        ]);
    }

    public function performance(Request $request)
    {
        $user = $request->user();
        $models = $this->resolveModels($user, $request);
        $category = $request->query('category', 'local');
        $range = $request->query('range', '1W');

        $holdings = $models->portfolio->where('user_id', $user->id)
            ->where('category', $category)
            ->get();

        $days = match ($range) {
            '1D' => 1,
            '1W' => 7,
            '1M' => 30,
            default => 7
        };

        $multiSeries = [];
        $totalCurrentValue = 0;

        foreach ($holdings as $holding) {
            $dataPoints = [];
            $now = now();

            for ($i = $days; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);
                $historicalPrice = $holding->market_price * (1 - ($i * 0.005));
                $totalQty = $holding->cleared_quantity + $holding->uncleared_quantity;

                $dataPoints[] = [
                    'x' => $date->timestamp * 1000,
                    'y' => round($totalQty * $historicalPrice, 2),
                ];
            }

            $multiSeries[] = [
                'name' => $holding->symbol,
                'data' => $dataPoints,
            ];

            $totalQty = $holding->cleared_quantity + $holding->uncleared_quantity;
            $totalCurrentValue += ($totalQty * $holding->market_price);
        }

        return response()->json([
            'success' => true,
            'series' => $multiSeries,
            'total' => $totalCurrentValue,
            'change' => 1.25,
        ]);
    }
}
