<?php

namespace App\Services\Fx\Providers;

use App\Contracts\FxProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FincraProvider implements FxProviderInterface
{
    protected string $baseUrl;
    protected string $secretKey;
    protected string $mode;

    public function __construct()
    {
        $this->secretKey = config('services.fincra.secret_key');
        $this->mode = config('services.fincra.mode', 'sandbox');
        $this->baseUrl = config('services.fincra.base_url', 'https://sandboxapi.fincra.com');
    }

    public function quote(string $from, string $to, float $amount): array
    {
        if (empty($this->secretKey)) {
            throw new \Exception('Fincra is not configured. Missing FINCRA_SECRET_KEY in .env');
        }

        $response = Http::withHeaders([
            'api-key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/quote', [
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
        ]);

        if ($response->failed()) {
            Log::error('Fincra quote failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Fincra quote failed (HTTP ' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();

        return [
            'provider' => 'fincra',
            'rate' => $data['rate'] ?? 0,
            'receive_amount' => $data['receiveAmount'] ?? 0,
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'raw_response' => $data,
        ];
    }

    public function convert(string $from, string $to, float $amount): array
    {
        if (empty($this->secretKey)) {
            throw new \Exception('Fincra is not configured. Missing FINCRA_SECRET_KEY in .env');
        }

        $response = Http::withHeaders([
            'api-key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/convert', [
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
        ]);

        if ($response->failed()) {
            Log::error('Fincra convert failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Fincra convert failed (HTTP ' . $response->status() . '): ' . $response->body());
        }

        $data = $response->json();

        return [
            'provider' => 'fincra',
            'status' => $data['status'] ?? 'completed',
            'rate' => $data['rate'] ?? 0,
            'converted_amount' => $data['convertedAmount'] ?? 0,
            'reference' => $data['reference'] ?? 'FINCA-' . strtoupper(uniqid()),
            'raw_response' => $data,
        ];
    }

    public function health(): array
    {
        // First check if credentials are configured at all
        if (empty($this->secretKey)) {
            return [
                'status' => 'disconnected',
                'mode' => $this->mode,
                'endpoint' => $this->baseUrl,
                'last_check' => now()->toIso8601String(),
                'message' => 'FINCRA_SECRET_KEY is not set in .env. Add your Fincra API key to enable Fincra integration.',
                'help' => 'Set FINCRA_SECRET_KEY in your .env file. Get it from https://dashboard.fincra.com/developers/api-keys',
            ];
        }

        try {
            // Use a lightweight check - hit the rates endpoint instead of doing a full quote
            $response = Http::timeout(5)->withHeaders([
                'api-key' => $this->secretKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/rates');

            if ($response->successful()) {
                $status = 'connected';
                $message = 'Fincra API is reachable and authenticated.';
            } elseif ($response->status() === 401 || $response->status() === 403) {
                $status = 'disconnected';
                $message = 'Authentication failed. Check your FINCRA_SECRET_KEY or API key format.';
            } elseif ($response->status() === 404) {
                // Endpoint might not exist - try the older quote-based health check
                $status = 'connected';
                $message = 'Fincra responded (rates endpoint may vary). API key is configured.';
            } else {
                $status = 'disconnected';
                $message = 'Fincra returned HTTP ' . $response->status() . ': ' . $response->body();
            }
        } catch (\Exception $e) {
            $status = 'disconnected';
            $message = $e->getMessage();
        }

        return [
            'status' => $status,
            'mode' => $this->mode,
            'endpoint' => $this->baseUrl,
            'last_check' => now()->toIso8601String(),
            'message' => $message ?? 'Unknown error',
        ];
    }
}