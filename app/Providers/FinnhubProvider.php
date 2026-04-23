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
}
