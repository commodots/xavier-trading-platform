<?php

namespace App\Http\Controllers;

use App\Services\Stocks\Contracts\MarketDataProvider;
use App\Services\Stocks\Contracts\StockBroker;

class StockController extends Controller
{
    public function __construct(
        protected StockBroker $broker,
        protected MarketDataProvider $market
    ) {}

    public function portfolio()
    {
        return response()->json(
            $this->broker->portfolio(auth()->id())
        );
    }

    public function quote($symbol)
    {
        return response()->json(
            $this->market->quote($symbol)
        );
    }
}
