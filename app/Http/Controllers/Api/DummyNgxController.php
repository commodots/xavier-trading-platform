<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DummyNgxController extends Controller
{
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

        $order_id = 'NGX' . time() . rand(1000, 9999);

        return response()->json([
            'order_id' => $order_id,
            'status' => 'accepted',
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
}