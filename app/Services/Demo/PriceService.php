<?php

namespace App\Services\Demo;

class PriceService
{
    public function getCurrentPrice($symbol, $marketType)
    {
        // Later connect real API
        // For now simulate

        if ($marketType === 'local') {
            return rand(100, 1000);
        }

        return rand(10, 300); // international
    }
}