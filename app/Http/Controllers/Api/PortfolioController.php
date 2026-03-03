<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Portfolio;
use App\Models\Demo\DemoPortfolio;
use App\Services\Demo\DemoTradingService;
use App\Services\LiveTradingService;


class PortfolioController extends Controller
{
    private function resolveModels($user)
    {
        $isDemo = $user->trading_mode === 'demo';
        return (object) [
            'portfolio' => $isDemo ? new DemoPortfolio() : new Portfolio(),
        ];
    }

   public function index(Request $request)
    {
        $user = $request->user();

        // Route to the appropriate service provider
        $service = ($user->trading_mode === 'demo') 
            ? app(DemoTradingService::class) 
            : app(LiveTradingService::class);

        return response()->json([
            'success' => true,
            'data'    => $service->getPortfolio($user->id)
        ]);
    }

    public function performance(Request $request)
    {
        $user = $request->user();
        $models = $this->resolveModels($user);

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
                    'y' => round($totalQty * $historicalPrice, 2)
                ];
            }

            $multiSeries[] = [
                'name' => $holding->symbol,
                'data' => $dataPoints
            ];

            $totalQty = $holding->cleared_quantity + $holding->uncleared_quantity;
            $totalCurrentValue += ($totalQty * $holding->market_price);
        }

        return response()->json([
            'success' => true,
            'series' => $multiSeries,
            'total' => $totalCurrentValue,
            'change' => 1.25
        ]);
    }
}
