<?php

namespace App\Services;

use App\Models\FxConfig;
use App\Models\FxRate;

class FxEngine
{
    public function calculateEffectiveRate(float $baseRate): array
    {
        if ($baseRate <= 0) {
            throw new \InvalidArgumentException('Base rate must be positive.');
        }

        $config = FxConfig::first();

        $volatility = $this->getVolatility();

        $dynamicMarkup = (float) ($config?->target_margin_percent ?? 2);
        $minMarkup     = (float) ($config?->min_markup ?? 1);
        $maxMarkup     = (float) ($config?->max_markup ?? 5);

        if ($volatility > (float) ($config?->volatility_threshold ?? 3)) {
            $dynamicMarkup += 1;
        }

        $dynamicMarkup = max($minMarkup, min($dynamicMarkup, $maxMarkup));

        $effectiveRate = $baseRate * (1 + $dynamicMarkup / 100);

        return [
            'effective_rate' => $effectiveRate,
            'markup_used'    => $dynamicMarkup,
        ];
    }

    private function getVolatility(): float
    {
        $rates = FxRate::latest()->take(5)->pluck('base_rate');

        if ($rates->count() < 2) {
            return 0.0;
        }

        $min = (float) $rates->min();
        if ($min <= 0) {
            return 0.0;
        }

        return abs(($rates->max() - $min) / $min * 100);
    }
}
