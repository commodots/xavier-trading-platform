<?php

namespace App\Services\Fx\Contracts;

interface ExchangeRateProvider
{
    public function latest(string $base = 'USD'): array;
    public function convert(string $from, string $to, float $amount): float;
}
