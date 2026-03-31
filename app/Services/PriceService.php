<?php

namespace App\Services;

class PriceService
{
    public function getCurrentPrice($symbol, $marketType)
    {
        // Define Base Prices
        $basePrices = [
            'MTNN' => 245.50,
            'DANGCEM' => 320.00,
            'ZENITH' => 35.20,
            'GTCO' => 42.10,
            'TSLA' => 175.40,
            'AAPL' => 189.10,
            'NVDA' => 820.50,
            'BTC' => 64250.00,
            'ETH' => 3450.00,
            'SOL' => 145.00,
            'BNB' => 580.00,
            'XRP' => 0.62,
            'ADA' => 0.45,
            'DOGE' => 0.16,
            'DOT' => 7.10,
            'TRX' => 0.12,
            'LINK' => 18.50,
            'MATIC' => 0.72,
        ];

        $basePrice = $basePrices[strtoupper($symbol)] ?? 100.00;

        // If it's the NGX (local), simulate the fluctuation 
        // to match DummyNgxController logic
        if ($marketType === 'local') {
            // Apply a random fluctuation between -10 and +10 to the base price
            // This mimics controller's: 150.00 + rand(-10, 10)
            return $basePrice + rand(-5, 5); 
        }

        // For International/Crypto, return base price (or add similar logic)
        return $basePrice;
    }
}