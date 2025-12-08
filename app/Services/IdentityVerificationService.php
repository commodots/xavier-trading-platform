<?php 
namespace App\Services;

use Illuminate\Support\Facades\Http;

class IdentityVerificationService
{
    public static function verify($idType, $idValue)
    {
        $token = env('QOREID_API_KEY');
        $response = null;

        if ($idType === 'bvn') {
            $url = env('BVN_PROVIDER_URL') . '/' . $idValue;
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
            ])->get($url);
        } elseif ($idType === 'vnin' || $idType === 'nin') {
            // ✅ Correct endpoint for NIN/vNIN
            $url = 'https://api.qoreid.com/v1/ng/identities/nin/verify';
            $payload = [$idType => $idValue];

            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($url, $payload);
        } else {
            return [
                'success' => false,
                'message' => "Unsupported ID type: $idType"
            ];
        }

        if ($response->successful()) {
            return [
                'success' => true,
                'type' => $idType,
                'data' => $response->json('data'),
            ];
        }

        return [
            'success' => false,
            'message' => 'Verification failed: ' . ($response->json('message') ?? $response->body()),
        ];
    }
}
