<?php

namespace App\Services\FX\Contracts;

interface ExchangeRateProvider
{
    public function latest(string $base = 'USD'): array;
    public function convert(string $from, string $to, float $amount): float;
}
