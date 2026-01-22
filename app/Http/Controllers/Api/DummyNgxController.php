<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DummyNgxController extends Controller
{
    public function addBusinessDays(Carbon $date, int $days = 1): Carbon
    {
        $addedDays = 0;
        while ($addedDays < $days) {
            $date->addDay();
            // Skip weekends (Saturday=6, Sunday=0)
            if ($date->dayOfWeek >= 1 && $date->dayOfWeek <= 5) {
                $addedDays++;
            }
        }
        return $date;
    }
    public function marketData($symbol)
    {
        return response()->json([
            'symbol' => strtoupper($symbol),
            'bid' => 150.00 + rand(-10, 10),
            'ask' => 150.00 + rand(-10, 10),
            'last' => 150.00,
            'volume' => rand(1000, 10000),
        ]);
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'side' => 'required|in:buy,sell',
            'type' => 'required|in:limit,market',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric',
        ]);

        $order_id = 'NGX' . rand(100, 999);
        $trade_date = Carbon::now()->toDateString();
        $settlement_date = $this->addBusinessDays(Carbon::now(), 2)->toDateString();

        return response()->json([
            'order_id' => $order_id,
            'status' => 'accepted',
            'trade_date' => $trade_date,
            'settlement_date' => $settlement_date,
        ]);
    }

    public function orderStatus($order_id)
    {
        return response()->json([
            'order_id' => $order_id,
            'status' => 'filled',
            'filled_quantity' => 100,
            'price' => 150.00,
        ]);
    }

    // New endpoints for T+2 settlement

    public function tradeStatus($trade_id)
    {
        // Dummy: randomly return pending or settled
        $status = rand(0, 1) ? 'pending' : 'settled';

        return response()->json([
            'status' => $status,
        ]);
    }

    public function settleTrade($trade_id)
    {
        // Dummy: simulate NGX clearing
        // In real implementation, this would trigger settlement

        return response()->json([
            'success' => true,
            'message' => 'Settlement triggered for trade ' . $trade_id,
        ]);
    }

    public function marketQuotes()
    {
        return response()->json([
            'ZENITH' => ['symbol' => 'ZENITH', 'price' => rand(4000, 6000)/100],
            'GTCO' => ['symbol' => 'GTCO', 'price' => rand(3000, 5500)/100],
            'DANGCEM' => ['symbol' => 'DANGCEM', 'price' => rand(3000, 5500)/100],
        ]);
    }

    public function tradeHistory(Request $request)
    {
        $user_id = $request->query('user_id');

        // Dummy data
        $trades = [
            [
                'id' => 'NGX123456',
                'user_id' => $user_id,
                'symbol' => 'ZENITH',
                'side' => 'buy',
                'quantity' => 100,
                'price' => 45.50,
                'status' => 'settled',
                'trade_date' => '2026-01-20',
                'settlement_date' => '2026-01-22',
            ],
            [
                'id' => 'NGX123457',
                'user_id' => $user_id,
                'symbol' => 'GTCO',
                'side' => 'sell',
                'quantity' => 50,
                'price' => 35.20,
                'status' => 'pending',
                'trade_date' => '2026-01-21',
                'settlement_date' => '2026-01-23',
            ],
        ];

        return response()->json($trades);
    }
}