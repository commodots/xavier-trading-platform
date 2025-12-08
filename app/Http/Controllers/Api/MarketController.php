<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class MarketController extends Controller
{
    public function ngx()
    {
        $data = [
            ["symbol" => "ZENITH", "name" => "Zenith Bank", "price" => rand(4000, 6000)/100],
            ["symbol" => "GTCO", "name" => "GTCO Holdings", "price" => rand(3000, 5500)/100],
            ["symbol" => "ACCESS", "name" => "Access Bank", "price" => rand(3000, 5500)/100]
        ];

        return response()->json(["success" => true, "data" => $data]);
    }

    public function global()
    {
        $data = [
            ["symbol" => "AAPL", "name" => "Apple Inc", "price" => rand(15000, 19000)/100],
            ["symbol" => "TSLA", "name" => "Tesla Inc", "price" => rand(60000, 90000)/100],
            ["symbol" => "AMZN", "name" => "Amazon", "price" => rand(10000, 15000)/100],
        ];

        return response()->json(["success" => true, "data" => $data]);
    }

    public function crypto()
    {
        $data = [
            ["symbol" => "BTC", "name" => "Bitcoin", "price" => rand(35000000, 40000000)],
            ["symbol" => "ETH", "name" => "Ethereum", "price" => rand(2100000, 2300000)],
            ["symbol" => "USDT", "name" => "Tether USD", "price" => 1500],
        ];

        return response()->json(["success" => true, "data" => $data]);
    }
}
