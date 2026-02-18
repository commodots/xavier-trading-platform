<?php

namespace App\Services\Demo;

class PriceService
{
    public function getCurrentPrice($symbol, $marketType)
    {
        // Later, replace this array with a real API call 

        // For now, simulate exact prices so the backend math matches the frontend UI exactly.
        $dummyPrices = [
            'MTNN' => 245.50,
            'DANGCEM' => 320.00,
            'ZENITH' => 35.20,
            'TSLA' => 175.40,
            'AAPL' => 189.10,
            'NVDA' => 820.50,
            'BTC' => 64250.00,
            'ETH' => 3450.00,
            'SOL' => 145.00,
            'FGNSB_2027' => 1000.00,
            'CP_MTN_I' => 1000.00,
            'ABB2026S0' => 1000.00,
        ];

        // Return the exact price, or fallback to 100 if a random ticker is sent
        return $dummyPrices[$symbol] ?? 100.00;
    }
}