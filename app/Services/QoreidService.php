<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QoreidService
{
    protected string $baseUrl;

    protected string $clientId;

    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.qoreid.base_url');
        $this->clientId = config('services.qoreid.client_id');
        $this->clientSecret = config('services.qoreid.client_secret');
    }

    /**
     * Get QoreID access token
     */
    public static function getAccessToken()
    {
        if (config('services.qoreid.dummy_mode', false)) {
            return 'dummy_token_12345';
        }

        return Cache::remember('qoreid_access_token', 3500, function () {
            // QoreID standard endpoint is usually /token
            $url = rtrim(config('services.qoreid.base_url'), '/') . '/token';

            $response = Http::post($url, [
                'clientId' => config('services.qoreid.client_id'),
                'secret'   => config('services.qoreid.client_secret'),
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to get QoreID token: ' . $response->body());
            }

            return $response->json()['accessToken'] ?? null;
        });
    }

    /**
     * Consolidated Identity Verification (BVN or NIN/vNIN)
     */
    public static function verify(string $idType, string $idValue)
    {
        if (config('services.qoreid.dummy_mode', false)) {
            return ['success' => true, 'data' => ['id' => $idValue]];
        }

        $token = self::getAccessToken();
        $baseUrl = rtrim(config('services.qoreid.base_url'), '/');
        
        $endpoint = match ($idType) {
            'bvn' => "$baseUrl/v1/ng/identities/bvn/$idValue",
            'nin', 'vnin' => "$baseUrl/v1/ng/identities/nin/$idValue",
            default => throw new Exception("Unsupported ID type: $idType"),
        };

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($endpoint);

        if ($response->failed()) {
            Log::error('QoreID Verification Error', [
                'id_type' => $idType,
                'response' => $response->body()
            ]);
            return [
                'success' => false, 
                'message' => 'Verification failed: ' . ($response->json('message') ?? 'Unknown error')
            ];
        }

        return ['success' => true, 'data' => $response->json()];
    }

    /**
     * The 2FA Implementation: BVN + NIN + Selfie Match    
     */
   public static function verify2FA($bvn, $nin, $imagePath, $userData = [])
    {
        // ✅ Dummy data mode
        if (config('services.qoreid.dummy_mode', false)) {
            return [
                'success' => true,
                'is_match' => true, // Simulate successful face match
                'data' => [
                    'firstname' => 'Aisha',
                    'lastname' => 'Ogunleye',
                    'bvn' => $bvn,
                    'nin' => $nin,
                ]
            ];
        }

        $token = self::getAccessToken();
        $url = rtrim(config('services.qoreid.base_url'), '/') . '/v1/ng/identities/complex-verification';

        if (!$imagePath || !file_exists($imagePath)) {
            throw new Exception("Selfie image file not found.");
        }

        $payload = [
            'firstname' => $userData['firstname'] ?? '',
            'lastname'  => $userData['lastname'] ?? '',
            'field' => [
                'bvn' => $bvn,
                'nin' => $nin
            ],
            'image' => base64_encode(file_get_contents($imagePath)),
            'imageType' => 'SELFIE'
        ];

        $response = Http::withToken($token)->post($url, $payload);

        if ($response->failed()) {
            throw new Exception('QoreID API Error: ' . $response->body());
        }

        $result = $response->json();

        return [
            'success'  => $response->successful(),
            // QoreID returns biometrics match status here
            'is_match' => $result['summary']['biometrics']['match'] ?? false,
            'data'     => $result
        ];
    }
}
