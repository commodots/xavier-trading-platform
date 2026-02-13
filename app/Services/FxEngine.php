<?php

namespace App\Services;

use App\Models\FxConfig;
use App\Models\FxRate;

class FxEngine
{
    public function calculateEffectiveRate($baseRate)
    {
        $config = FxConfig::first();

        $volatility = $this->getVolatility();

        $dynamicMarkup = $config->target_margin_percent ?? 2;

        if ($volatility > ($config->volatility_threshold ?? 3)) {
            $dynamicMarkup += 1;
        }

        if ($dynamicMarkup > ($config->max_markup ?? 5)) {
            $dynamicMarkup = $config->max_markup;
        }

        if ($dynamicMarkup < ($config->min_markup ?? 1)) {
            $dynamicMarkup = $config->min_markup;
        }

        $effectiveRate = $baseRate + ($baseRate * ($dynamicMarkup / 100));

        return [
            'effective_rate' => $effectiveRate,
            'markup_used' => $dynamicMarkup,
        ];
    }

    private function getVolatility()
    {
        $rates = FxRate::latest()->take(5)->pluck('base_rate');

        if ($rates->count() < 2) {
            return 0;
        }

        return abs(($rates->max() - $rates->min()) / $rates->min() * 100);
    }
}
