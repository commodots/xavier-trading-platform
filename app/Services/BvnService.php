<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BvnService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = env('QOREID_BASE_URL', 'https://api.qoreid.com');
        $this->clientId = env('QOREID_CLIENT_ID');
        $this->clientSecret = env('QOREID_CLIENT_SECRET');
    }

    private function getAccessToken(): ?string
    {
        $response = Http::asForm()->post($this->baseUrl . '/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            Log::error('QoreID Auth Error', ['response' => $response->json()]);
            return null;
        }

        return $response->json()['access_token'] ?? null;
    }

    public function verifyIdentity(string $idType, string $idValue): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to get QoreID token'];
        }

        $endpoint = match ($idType) {
            'bvn' => $this->baseUrl . "/v1/ng/identities/bvn/$idValue",
            'nin', 'vnin' => $this->baseUrl . "/v1/ng/identities/nin/$idValue",
            default => null,
        };

        if (!$endpoint) {
            return ['success' => false, 'message' => 'Invalid ID type'];
        }

        $response = Http::withToken($token)->get($endpoint);

        if ($response->failed()) {
            Log::error('QoreID Verification Error', ['response' => $response->json()]);
            return ['success' => false, 'message' => 'Verification failed: ' . $response->body()];
        }

        return $response->json();
    }
}
