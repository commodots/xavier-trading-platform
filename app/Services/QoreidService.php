<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class QoreidService
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

    /**
     * Get QoreID access token
     */
    public static function getAccessToken()
    {
        if (env('QOREID_DUMMY_MODE', false)) {
            return 'dummy_token_12345';
        }

        $url = "https://api.qoreid.com/token";

        $payload = [
            "clientId" => env('QOREID_CLIENT_ID'),
            "secret" => env('QOREID_CLIENT_SECRET'),
            "grantType" => "client_credentials"
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) {
            throw new Exception("Failed to get QoreID token: " . $response->body());
        }

        return $response->json()['accessToken'] ?? null;
    }

    /**
     * Verify identity (BVN / vNIN)
     */
    public static function verifyIdentity($idType, $idValue)
    {
        // ✅ Dummy data mode (for local/dev use)
        if (env('QOREID_DUMMY_MODE', false)) {
            return [
                "success" => true,
                "provider" => "QoreID-Dummy",
                "id_type" => $idType,
                "id_value" => $idValue,
                "data" => [
                    "first_name" => "Aisha",
                    "last_name" => "Ogunleye",
                    "middle_name" => "Temitope",
                    "date_of_birth" => "1994-07-15",
                    "gender" => "Female",
                    "phone_number" => "+2348089001122",
                    "email" => "aisha.ogunleye@example.com",
                    "photo" => "https://randomuser.me/api/portraits/women/44.jpg",
                    "bvn" => "12345678901",
                    "nin" => "98765432109",
                    "address" => "26B Road 38, Lekki Scheme 2, Lagos",
                    "status" => "verified",
                ]
            ];
        }

        // 🧩 Real API mode
        $token = self::getAccessToken();
        $baseUrl = "https://api.qoreid.com/v1/ng/identities";

        if ($idType === 'vnin') {
            $url = "$baseUrl/virtual-nin/vNIN";
            $payload = [
                "id" => $idValue,
                "isSubjectConsent" => true
            ];
        } elseif ($idType === 'bvn') {
            $url = "$baseUrl/bvn/" . $idValue;
            $payload = [];
        } else {
            throw new Exception("Unsupported ID type: $idType");
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) {
            throw new Exception("Verification failed: " . $response->body());
        }

        return $response->json();
    }
}
