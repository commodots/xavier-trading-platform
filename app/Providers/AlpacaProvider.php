<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;

class AlpacaProvider
{
    protected $key;

    protected $secret;

    protected $baseUrl;

    protected $dataBaseUrl;

    public function __construct()
    {
        $this->key = env('ALPACA_API_KEY');
        $this->secret = env('ALPACA_SECRET_KEY');
        $this->baseUrl = env('ALPACA_BASE_URL', 'https://paper-api.alpaca.markets');
        $this->dataBaseUrl = 'https://data.alpaca.markets/v2';
    }

    protected int $timeout = 10;

    public function quote(string $symbol): float
    {
        if (! $this->key || ! $this->secret) {
            return 0.0;
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->dataBaseUrl.'/stocks/'.strtoupper($symbol).'/latest/quote');

                if ($response->successful()) {
                $data = $response->json();
               
                return (float) ($data['quote']['ap'] ?? $data['quote']['bp'] ?? 0.0);
            }
            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function quoteDetails(string $symbol): array
    {
        if (! $this->key || ! $this->secret) {
            return [
                'symbol' => strtoupper($symbol),
                'price' => 0.0,
                'previous_close' => 0.0,
                'change' => 0.0,
                'timestamp' => now()->toISOString(),
            ];
        }

        try {
            $data = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->baseUrl.'/v2/stocks/'.strtoupper($symbol).'/latest/quote')
                ->json();

            $current = (float) ($data['quote']['ap'] ?? $data['quote']['lp'] ?? 0.0);
            $previousClose = $this->getPreviousClose($symbol);
            $change = $previousClose > 0 ? round((($current - $previousClose) / $previousClose) * 100, 2) : 0.0;

            return [
                'symbol' => strtoupper($symbol),
                'price' => $current,
                'previous_close' => $previousClose,
                'change' => $change,
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'symbol' => strtoupper($symbol),
                'price' => 0.0,
                'previous_close' => 0.0,
                'change' => 0.0,
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    protected function getPreviousClose(string $symbol): float
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get($this->baseUrl.'/v2/stocks/'.strtoupper($symbol).'/bars', [
                    'timeframe' => '1Day',
                    'start' => now()->subDays(7)->toDateString(),
                    'end' => now()->toDateString(),
                    'limit' => 2,
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $bars = $payload['bars'] ?? $payload;
                if (is_array($bars) && count($bars) > 0) {
                    $latest = end($bars);

                    return (float) ($latest['c'] ?? 0.0);
                }
            }
        } catch (\Exception $e) {
        }

        return 0.0;
    }

    public function placeOrder($symbol, $qty, $side)
    {
        return $this->placeAdvancedOrder([
            'symbol' => $symbol,
            'qty' => $qty,
            'side' => $side,
            'type' => 'market',
            'time_in_force' => 'gtc',
        ]);
    }

    public function placeAdvancedOrder($data)
    {
        return Http::withHeaders($this->headers())
            ->post($this->baseUrl.'/v2/orders', $data)
            ->json();
    }

    public function getOrders()
    {
        return Http::withHeaders($this->headers())->get($this->baseUrl.'/v2/orders')->json();
    }

    public function getPositions()
    {
        return Http::withHeaders($this->headers())
            ->get($this->baseUrl.'/v2/positions')
            ->json();
    }

    public function getAccount()
    {
        return Http::withHeaders($this->headers())
            ->get($this->baseUrl.'/v2/account')
            ->json();
    }

    protected function headers()
    {
        return [
            'APCA-API-KEY-ID' => $this->key,
            'APCA-API-SECRET-KEY' => $this->secret,
        ];
    }
}
