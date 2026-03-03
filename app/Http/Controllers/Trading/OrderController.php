<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\LiveTradingService;
use App\Services\Demo\DemoTradingService;
use App\Models\Order;
use App\Models\Demo\DemoOrder;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'symbol'       => 'required|string',
            'company'      => 'required|string',
            'units'        => 'required|numeric|min:0',
            'amount'       => 'required|numeric|min:0',
            'type'         => 'required|in:buy,sell',
            'market'       => 'required|string',
            'market_price' => 'required|numeric',
            'currency'     => 'required|string',
        ]);

        // Decide which service to use
        $service = ($user->trading_mode === 'demo') 
            ? app(DemoTradingService::class) 
            : app(LiveTradingService::class);

        try {
            $order = $service->executeTrade($user, $validated);
            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        // Dynamic Model selection for the 'show' detail
        $model = ($user->trading_mode === 'demo') ? new DemoOrder() : new Order();
        
        $order = $model->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        
        return response()->json([
            'success' => true,
            'data' => $order->load('trades')
        ]);
    }
}