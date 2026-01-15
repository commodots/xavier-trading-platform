<?php

namespace App\Services\Payments\Paystack;

use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService implements PaymentGateway
{
    private string $secretKey;
    private string $publicKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->baseUrl = 'https://api.paystack.co';
    }

    public function transfer(array $data): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/transfer", [
                    'source' => 'balance',
                    'amount' => $data['amount'] * 100, // Convert to kobo
                    'recipient' => $data['recipient_code'],
                    'reason' => $data['reason'] ?? 'Transfer from Xavier Trading Platform',
                ]);

            $result = $response->json();

            if ($response->failed()) {
                Log::error('Paystack transfer failed', [
                    'error' => $result,
                    'data' => $data
                ]);

                return [
                    'status' => 'failed',
                    'message' => $result['message'] ?? 'Transfer failed',
                ];
            }

            return [
                'reference' => $result['data']['reference'],
                'status' => $result['data']['status'],
                'amount' => $data['amount'],
                'currency' => 'NGN',
                'created_at' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Paystack transfer exception', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'status' => 'failed',
                'message' => 'Transfer service unavailable',
            ];
        }
    }

    public function createVirtualAccount(array $data): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/dedicated_account", [
                    'customer' => $data['customer_id'] ?? $data['email'],
                    'preferred_bank' => $data['bank'] ?? 'wema-bank', // Default bank
                ]);

            $result = $response->json();

            if ($response->failed()) {
                Log::error('Paystack virtual account creation failed', [
                    'error' => $result,
                    'data' => $data
                ]);

                return [
                    'status' => 'failed',
                    'message' => $result['message'] ?? 'Virtual account creation failed',
                ];
            }

            return [
                'account_number' => $result['data']['account_number'],
                'bank' => $result['data']['bank']['name'],
                'account_name' => $result['data']['account_name'],
                'status' => 'ACTIVE',
            ];
        } catch (\Exception $e) {
            Log::error('Paystack virtual account creation exception', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'status' => 'failed',
                'message' => 'Virtual account service unavailable',
            ];
        }
    }

    public function getBalance(): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/balance");

            $result = $response->json();

            if ($response->failed()) {
                Log::error('Paystack balance check failed', [
                    'error' => $result
                ]);

                return [
                    'currency' => 'NGN',
                    'balance' => 0,
                    'error' => $result['message'] ?? 'Balance check failed',
                ];
            }

            $balance = $result['data'][0]['balance'] / 100; // Convert from kobo

            return [
                'currency' => 'NGN',
                'balance' => $balance,
            ];
        } catch (\Exception $e) {
            Log::error('Paystack balance check exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'currency' => 'NGN',
                'balance' => 0,
                'error' => 'Balance service unavailable',
            ];
        }
    }

    /**
     * Initialize a payment transaction
     */
    public function initializePayment(array $data): array
    {
        try {
            $payload = [
                'email' => $data['email'],
                'amount' => $data['amount'] * 100, // Convert to kobo
                'reference' => $data['reference'],
                'callback_url' => $data['callback_url'] ?? config('services.paystack.callback_url'),
                'metadata' => $data['metadata'] ?? [],
            ];

            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/transaction/initialize", $payload);

            $result = $response->json();

            if ($response->failed()) {
                Log::error('Paystack payment initialization failed', [
                    'error' => $result,
                    'data' => $data
                ]);

                return [
                    'status' => 'failed',
                    'message' => $result['message'] ?? 'Payment initialization failed',
                ];
            }

            return [
                'status' => 'success',
                'reference' => $result['data']['reference'],
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code'],
            ];
        } catch (\Exception $e) {
            Log::error('Paystack payment initialization exception', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'status' => 'failed',
                'message' => 'Payment initialization service unavailable',
            ];
        }
    }

    /**
     * Verify a payment transaction
     */
    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            $result = $response->json();

            if ($response->failed()) {
                Log::error('Paystack payment verification failed', [
                    'error' => $result,
                    'reference' => $reference
                ]);

                return [
                    'status' => 'failed',
                    'message' => $result['message'] ?? 'Payment verification failed',
                ];
            }

            return [
                'status' => $result['data']['status'],
                'reference' => $result['data']['reference'],
                'amount' => $result['data']['amount'] / 100, // Convert from kobo
                'currency' => $result['data']['currency'],
                'metadata' => $result['data']['metadata'] ?? [],
                'paid_at' => $result['data']['paid_at'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Paystack payment verification exception', [
                'error' => $e->getMessage(),
                'reference' => $reference
            ]);

            return [
                'status' => 'failed',
                'message' => 'Payment verification service unavailable',
            ];
        }
    }
}