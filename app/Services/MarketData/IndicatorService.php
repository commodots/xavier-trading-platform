<?php

namespace App\Services\MarketData;

class IndicatorService
{
    public function movingAverage(array $candles, int $period = 14)
    {
        $result = [];

        foreach ($candles as $i => $candle) {
            if ($i < $period) {
                $result[] = null;
                continue;
            }

            $slice = array_slice($candles, $i - $period, $period);
            $avg = array_sum(array_column($slice, 'close')) / $period;
            $result[] = round($avg, 2);
        }

        return $result;
    }
}
