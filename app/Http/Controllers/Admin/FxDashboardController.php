<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\Request;

class FxDashboardController extends Controller
{
    public function index(Request $request)
    {
        $todayProfit = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->whereDate('created_at', today())
            ->sum('amount');

        $monthlyProfit = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $totalProfit = Ledger::where('type', 'FX_MARKUP_PROFIT')
            ->sum('amount');

        $totalVolume = Ledger::where('type', 'FX_CONVERSION')
            ->sum('amount');

        // Build 14-day daily profit series (dates + totals)
        $start = now()->subDays(13)->startOfDay();

        $rows = Ledger::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('type', 'FX_MARKUP_PROFIT')
            ->where('created_at', '>=', $start)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

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
                'todayProfit' => (float) $todayProfit,
                'monthlyProfit' => (float) $monthlyProfit,
                'totalProfit' => (float) $totalProfit,
                'totalVolume' => (float) $totalVolume,
                'daily' => ['labels' => $labels, 'data' => $series],
            ],
        ]);
    }
}
