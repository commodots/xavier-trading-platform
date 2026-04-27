<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;

class FinnhubProvider
{
    protected string $key;

    protected string $baseUrl;

    public function __construct()
    {
        $this->key = env('FINNHUB_API_KEY');
        $this->baseUrl = env('FINNHUB_BASE_URL', 'https://finnhub.io');
    }

    public function quote(string $symbol): float
    {
        $data = $this->getQuote($symbol);

        return (float) ($data['c'] ?? 0.0);
    }

    public function getQuote(string $symbol): array
    {
        if (! $this->key) {
            return [];
        }

        try {
            $response = Http::timeout(10)->get($this->baseUrl.'/api/v1/quote', [
                'symbol' => strtoupper($symbol),
                'token' => $this->key,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            // Swallow exceptions here and allow fallback behavior in higher layers.
        }

        return [];
    }

    public function quoteDetails(string $symbol): array
    {
        $data = $this->getQuote($symbol);

        $price = (float) ($data['c'] ?? 0.0);
        $previousClose = (float) ($data['pc'] ?? 0.0);
        $change = $previousClose > 0 ? round((($price - $previousClose) / $previousClose) * 100, 2) : 0.0;

        return [
            'symbol' => strtoupper($symbol),
            'price' => $price,
            'open' => (float) ($data['o'] ?? 0.0),
            'high' => (float) ($data['h'] ?? 0.0),
            'low' => (float) ($data['l'] ?? 0.0),
            'previous_close' => $previousClose,
            'change' => $change,
            'timestamp' => now()->toISOString(),
        ];
    }
}
