<?php

namespace App\Services\MarketData;

use App\Providers\FinnhubProvider;

class StockMarketService
{
    public function candles(string $symbol, string $interval = '1D', int $limit = 100)
    {
        $provider = new FinnhubProvider;
        $resolution = $this->normalizeInterval($interval);
        [$from, $to] = $this->buildRange($resolution, $limit);

        $response = $provider->candles($symbol, $resolution, $from, $to);

        if (! is_array($response) || ($response['s'] ?? '') !== 'ok' || empty($response['t'])) {
            return $this->generateFallbackCandles($limit);
        }

        return $this->formatFinnhubCandles($response);
    }

    protected function normalizeInterval(string $interval): string
    {
        $interval = strtolower(trim($interval));

        return match ($interval) {
            '1', '1m', '1min' => '1',
            '5', '5m', '5min' => '5',
            '15', '15m', '15min' => '15',
            '30', '30m', '30min' => '30',
            '60', '60m', '60min' => '60',
            '1d', 'd' => 'D',
            '1w', 'w' => 'W',
            default => '1',
        };
    }

    protected function buildRange(string $resolution, int $limit): array
    {
        $to = now()->timestamp;

        if (in_array($resolution, ['D', 'W'], true)) {
            $from = match ($resolution) {
                'W' => now()->subWeeks($limit)->timestamp,
                default => now()->subDays($limit)->timestamp,
            };
        } else {
            $from = now()->subMinutes($limit)->timestamp;
        }

        return [$from, $to];
    }

    protected function formatFinnhubCandles(array $data): array
    {
        $candles = [];

        foreach ($data['t'] as $index => $timestamp) {
            $candles[] = [
                'time' => (int) $timestamp * 1000,
                'open' => (float) ($data['o'][$index] ?? 0),
                'high' => (float) ($data['h'][$index] ?? 0),
                'low' => (float) ($data['l'][$index] ?? 0),
                'close' => (float) ($data['c'][$index] ?? 0),
                'volume' => (float) ($data['v'][$index] ?? 0),
            ];
        }

        return $candles;
    }

    protected function generateFallbackCandles(int $limit): array
    {
        $data = [];
        $time = now()->subMinutes($limit);

        for ($i = 0; $i < $limit; $i++) {
            $open = rand(150, 160);
            $close = rand(150, 160);
            $high = max($open, $close) + (rand(1, 100) / 100);
            $low = min($open, $close) - (rand(1, 100) / 100);
            $volume = rand(10000, 50000);

            $data[] = [
                'time' => $time->timestamp * 1000,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $volume,
            ];

            $time->addMinute();
        }

        return $data;
    }
}
