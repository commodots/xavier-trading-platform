<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\FxRate;
use Illuminate\Http\Request;

class FxDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get the latest base rate to calculate the NGN equivalent
        $latestFxRecord = FxRate::latest()->first();
        $latestRate = $latestFxRecord ? $latestFxRecord->base_rate : 1500;
        $latestMarkup = $latestFxRecord ? $latestFxRecord->markup_percent : 0;

        //  Today's Profit
        $todayProfitUsd = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->whereDate('created_at', today())
            ->sum('amount');

        //This Month's Profit
        $monthlyProfitUsd = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        //  Lifetime Profit
        $totalProfitUsd = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->sum('amount');

        // Total Volume (USD bought by users)
        $totalVolumeUsd = Ledger::where('type', 'FX_CONVERSION')
            ->sum('amount');

        // Build 14-day daily profit series
        $start = now()->subDays(13)->startOfDay();
        
        $profitLedgers = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->where('created_at', '>=', $start)
            ->get();

        $rows = $profitLedgers->groupBy(function($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function($dayGroup) {
            return $dayGroup->sum('amount');
        })->toArray();

        $labels = [];
        $series = [];
        
        for ($i = 0; $i < 14; $i++) {
            $d = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $d;
            $series[] = isset($rows[$d]) ? (float) $rows[$d] : 0.0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                // USD Values
                'todayProfit'      => (float) $todayProfitUsd,
                'monthlyProfit'    => (float) $monthlyProfitUsd,
                'totalProfit'      => (float) $totalProfitUsd,
                'totalVolume'      => (float) $totalVolumeUsd,
                
                // NGN Equivalents
                'todayProfitNgn'   => (float) ($todayProfitUsd * $latestRate),
                'monthlyProfitNgn' => (float) ($monthlyProfitUsd * $latestRate),
                'totalProfitNgn'   => (float) ($totalProfitUsd * $latestRate),
                'totalVolumeNgn'   => (float) ($totalVolumeUsd * $latestRate),
                
                'currentRate'      => $latestRate,
                'currentMarkup'    => (float) $latestMarkup,
                'daily'            => ['labels' => $labels, 'data' => $series],
            ],
        ]);
    }
}