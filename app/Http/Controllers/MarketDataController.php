<?php

namespace App\Http\Controllers;

use App\Services\Stocks\Contracts\MarketDataProvider;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function __construct(
        protected MarketDataProvider $market
    ) {}

    public function stockHistory(string $symbol, Request $request)
    {
        $range = $request->get('range', '7d');

        return response()->json([
            'success' => true,
            'data' => $this->market->historical($symbol, $range)
        ]);
    }
}
