<?php

namespace App\Services\Fx\Providers;

use App\Contracts\FxProviderInterface;
use App\Models\FxPair;

class ManualProvider implements FxProviderInterface
{
    public function quote(string $from, string $to, float $amount): array
    {
        $pair = FxPair::query()
            ->where('base_currency', $to)
            ->where('quote_currency', $from)
            ->where('active', true)
            ->firstOrFail();

        $rate = (float) $pair->buy_rate;
        $receiveAmount = $amount / $rate;

        return [
            'provider' => 'manual',
            'rate' => $rate,
            'receive_amount' => round($receiveAmount, 6),
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
        ];
    }

    public function convert(string $from, string $to, float $amount): array
    {
        $quote = $this->quote($from, $to, $amount);

        return array_merge($quote, [
            'status' => 'completed',
            'converted_amount' => $quote['receive_amount'],
            'reference' => 'MANUAL-' . strtoupper(uniqid()),
        ]);
    }
}