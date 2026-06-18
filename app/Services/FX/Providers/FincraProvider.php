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

        
        $businessId = config('services.fincra.business_id');

        $response = Http::withHeaders([
            'api-key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/quotes/generate', [
            'sourceCurrency' => strtoupper($from),
            'destinationCurrency' => strtoupper($to),
            'amount' => (string) $amount,
            'action' => 'send',
            'transactionType' => 'conversion',
            'business' => $businessId,
            'paymentDestination' => 'fliqpay_wallet',
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
            'rate' => $data['data']['rate'] ?? 0,
            'receive_amount' => $data['data']['amountToReceive'] ?? 0,
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'reference' => $data['data']['reference'] ?? null,
            'raw_response' => $data,
        ];
    }

    public function convert(string $from, string $to, float $amount): array
    {
        if (empty($this->secretKey)) {
            throw new \Exception('Fincra is not configured. Missing FINCRA_SECRET_KEY in .env');
        }

        
        $businessId = config('services.fincra.business_id');

        // Step 1: Generate a quote first
        $quoteResponse = Http::withHeaders([
            'api-key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/quotes/generate', [
            'sourceCurrency' => strtoupper($from),
            'destinationCurrency' => strtoupper($to),
            'amount' => (string) $amount,
            'action' => 'send',
            'transactionType' => 'conversion',
            'business' => $businessId,
            'paymentDestination' => 'fliqpay_wallet',
        ]);

        if ($quoteResponse->failed()) {
            Log::error('Fincra quote failed', [
                'status' => $quoteResponse->status(),
                'body' => $quoteResponse->body(),
            ]);
            throw new \Exception('Fincra quote failed (HTTP ' . $quoteResponse->status() . '): ' . $quoteResponse->body());
        }

        $quoteData = $quoteResponse->json();
        $quoteReference = $quoteData['data']['reference'] ?? null;

        if (!$quoteReference) {
            throw new \Exception('Fincra quote failed: No reference returned');
        }

        // Step 2: Initiate the conversion with the quote reference
        $convertResponse = Http::withHeaders([
            'api-key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/conversions/initiate', [
            'business' => $businessId,
            'quoteReference' => $quoteReference,
            'customerReference' => 'CUST-' . uniqid(),
        ]);

        if ($convertResponse->failed()) {
            Log::error('Fincra convert failed', [
                'status' => $convertResponse->status(),
                'body' => $convertResponse->body(),
            ]);
            throw new \Exception('Fincra convert failed (HTTP ' . $convertResponse->status() . '): ' . $convertResponse->body());
        }

        $convertData = $convertResponse->json();

        return [
            'provider' => 'fincra',
            'status' => 'completed',
            'rate' => $quoteData['data']['rate'] ?? 0,
            'converted_amount' => $quoteData['data']['amountToReceive'] ?? 0,
            'reference' => $convertData['data']['reference'] ?? 'FINCRA-' . strtoupper(uniqid()),
            'quote_reference' => $quoteReference,
            'raw_response' => [
                'quote' => $quoteData,
                'conversion' => $convertData,
            ],
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
            // Use a lightweight check - hit the quotes endpoint with POST (same as actual usage)
            $businessId = config('services.fincra.business_id');
            
            $payload = [
                'sourceCurrency' => 'USD',
                'destinationCurrency' => 'NGN',
                'amount' => '1',
                'action' => 'send',
                'transactionType' => 'conversion',
            ];
            
            // Add business ID 
            if ($businessId) {
                $payload['business'] = $businessId;
                $payload['paymentDestination'] = 'fliqpay_wallet';
            }
            
            $response = Http::timeout(10)->withHeaders([
                'api-key' => $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/quotes/generate', $payload);

            if ($response->successful()) {
                $status = 'connected';
                $message = 'Fincra API is reachable and authenticated.';
            } elseif ($response->status() === 401) {
                $status = 'disconnected';
                $message = 'Authentication failed. Check your FINCRA_SECRET_KEY or API key format.';
            } elseif ($response->status() === 403) {
                $status = 'disconnected';
                $message = 'Access denied. Check your FINCRA_SECRET_KEY or API key format.';
            } elseif ($response->status() === 404) {
                $status = 'disconnected';
                $message = 'Business not found. Configure FINCRA_BUSINESS_ID in .env with your actual business ID from Fincra dashboard.';
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