<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;

class AlpacaProvider
{
    protected $key;

    protected $secret;

    protected $baseUrl;

    public function __construct()
    {
        $this->key = env('ALPACA_API_KEY');
        $this->secret = env('ALPACA_SECRET_KEY');
        $this->baseUrl = env('ALPACA_BASE_URL', 'https://paper-api.alpaca.markets');
    }

    public function quote(string $symbol): float
    {
        $data = Http::withHeaders($this->headers())
            ->get($this->baseUrl.'/v2/stocks/'.strtoupper($symbol).'/quotes')
            ->json();

        return (float) ($data['askprice'] ?? 0.0);
    }

    public function quoteDetails(string $symbol): array
    {
        $data = Http::withHeaders($this->headers())
            ->get($this->baseUrl.'/v2/stocks/'.strtoupper($symbol).'/quotes')
            ->json();

        $current = (float) ($data['askprice'] ?? $data['last']['price'] ?? 0.0);
        $previousClose = $this->getPreviousClose($symbol);
        $change = $previousClose > 0 ? round((($current - $previousClose) / $previousClose) * 100, 2) : 0.0;

        return [
            'symbol' => strtoupper($symbol),
            'price' => $current,
            'previous_close' => $previousClose,
            'change' => $change,
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function getPreviousClose(string $symbol): float
    {
        try {
            $response = Http::withHeaders($this->headers())
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
            .
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
