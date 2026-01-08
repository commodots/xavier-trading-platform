<?php

namespace App\Services\Stocks\Contracts;

interface MarketDataProvider
{
    public function quote(string $symbol): array;

    public function historical(
        string $symbol,
        string $range = '7d'
    ): array;
}
