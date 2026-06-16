<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SettleTradeJob;
use App\Models\Trade;
use Illuminate\Http\Request;

class SettlementDashboardController extends Controller
{
    /**
     * Pending unsettled trades (excludes failed).
     */
    public function pending(Request $request)
    {
        $trades = Trade::unsettled()
            ->where(function ($q) {
                $q->whereNull('settlement_status')
                  ->orWhere('settlement_status', 'pending');
            })
            ->with('user', 'order')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($trades);
    }

    public function completed(Request $request)
    {
        $trades = Trade::where('settlement_status', 'settled')
            ->with('user', 'order')
            ->orderByDesc('settlement_date')
            ->paginate($request->get('per_page', 20));

        return response()->json($trades);
    }

    public function failed(Request $request)
    {
        $trades = Trade::where('settlement_status', 'failed')
            ->with('user', 'order')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($trades);
    }

    /**
     * Settlement metrics: pending, completed, failed counts.
     */
    public function metrics()
    {
        return response()->json([
            'pending' => Trade::unsettled()
                ->where(function ($q) {
                    $q->whereNull('settlement_status')
                      ->orWhere('settlement_status', 'pending');
                })->count(),
            'completed' => Trade::where('settlement_status', 'settled')->count(),
            'failed' => Trade::where('settlement_status', 'failed')->count(),
        ]);
    }

    /**
     * Complete settlement for a specific trade.
     */
    public function complete(Trade $trade)
    {
        dispatch(new SettleTradeJob($trade));

        return response()->json([
            "message" => "Settlement complete dispatched for trade #{$trade->id}",
        ]);
    }
}