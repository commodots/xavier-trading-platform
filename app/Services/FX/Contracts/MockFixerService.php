<?php

namespace App\Services\FX\Mock;

use App\Services\FX\Contracts\ExchangeRateProvider;

class MockFixerService implements ExchangeRateProvider
{
    protected array $rates = [
        'USD' => 1,
        'NGN' => 1550,
        'EUR' => 0.92,
        'GBP' => 0.78,
    ];

    public function latest(string $base = 'USD'): array
    {
        return [
            'base' => $base,
            'rates' => $this->rates,
            'date' => now()->toDateString(),
        ];
    }

    public function convert(string $from, string $to, float $amount): float
    {
        if (!isset($this->rates[$from], $this->rates[$to])) {
            return 0;
        }

        return round(($amount / $this->rates[$from]) * $this->rates[$to], 2);
    }
}
