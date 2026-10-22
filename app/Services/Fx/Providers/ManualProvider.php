<?php

namespace App\Services\Fx\Providers;

use App\Contracts\FxProviderInterface;
use App\Models\FxPair;

class ManualProvider implements FxProviderInterface
{
    public function quote(string $from, string $to, float $amount): array
    {
        $pair = FxPair::query()
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->where('active', true)
            ->first();

        if (! $pair) {
            // Try reverse direction
            $pair = FxPair::query()
                ->where('base_currency', $to)
                ->where('quote_currency', $from)
                ->where('active', true)
                ->firstOrFail();
        }

        $rate = (float) $pair->buy_rate;

        // NGN -> USD means we divide by the NGN per USD rate
        if ($from === 'NGN' && $to === 'USD') {
            $receiveAmount = $amount / $rate;
        } else {
            // USD -> NGN means we multiply by the NGN per USD rate
            $receiveAmount = $amount * $rate;
        }

        return [
            'provider' => 'manual',
            'rate' => $rate,
            'receive_amount' => round($receiveAmount, 6),
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
        ];
    }

    public function convert(string $from, string $to, float $amount, ?string $reference = null, ?array $quote = null): array
    {
        $quote ??= $this->quote($from, $to, $amount);

        return array_merge($quote, [
            'status' => 'completed',
            'converted_amount' => $quote['receive_amount'],
            'reference' => $reference ?? 'MANUAL-'.strtoupper(uniqid()),
        ]);
    }
}
