<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IdentityVerificationService
{
    public static function verify($idType, $idValue)
    {
        $token = config('services.qoreid.api_key');
        $response = null;

        if ($idType === 'bvn') {
            $url = config('services.qoreid.base_url').'/v1/ng/identities/bvn/'.$idValue;
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
                'Content-Type' => 'application/json',
            ])->post($url, $payload);
        } else {
            return [
                'success' => false,
                'message' => "Unsupported ID type: $idType",
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
            'message' => 'Verification failed: '.($response->json('message') ?? $response->body()),
        ];
    }
}
