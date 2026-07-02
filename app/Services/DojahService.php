<?php

namespace App\Services;

use App\Models\KycVerification;
use App\Services\Kyc\Providers\DojahProvider;

class DojahService
{
    public function __construct(private ?DojahProvider $provider = null)
    {
        $this->provider = $provider ?? app(DojahProvider::class);
    }

    private function isTestMode(): bool
    {
        return config('services.dojah.test_mode', false);
    }

    public function verifyBvn(string $bvn): array
    {
        if ($this->isTestMode()) {
            // Dojah sandbox test BVN - official test value
            $validTestBVN = '22222222222';

            if ($bvn === $validTestBVN) {
                return [
                    'success' => true,
                    'entity' => [
                        'id' => 'test_bvn_'.uniqid(),
                        'bvn' => $bvn,
                        'first_name' => 'Test',
                        'last_name' => 'User',
                        'gender' => 'Male',
                    ],
                    'message' => 'BVN verified successfully (test mode).',
                ];
            }

            return [
                'success' => false,
                'message' => 'BVN not found. Use test BVN: 22222222222',
            ];
        }

        try {
            return $this->provider->verifyBvn($bvn);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'BVN endpoint connection failure.'];
        }
    }

    public function verifyNin(string $nin): array
    {
        if ($this->isTestMode()) {
            // Dojah sandbox test NIN - official test value
            $validTestNIN = '70123456789';

            if ($nin === $validTestNIN) {
                return [
                    'success' => true,
                    'entity' => [
                        'id' => 'test_nin_'.uniqid(),
                        'nin' => $nin,
                        'first_name' => 'Test',
                        'last_name' => 'User',
                        'gender' => 'Male',
                    ],
                    'message' => 'NIN verified successfully (test mode).',
                ];
            }

            return [
                'success' => false,
                'message' => 'NIN not found. Use test NIN: 70123456789',
            ];
        }

        try {
            return $this->provider->verifyNin($nin);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'NIN endpoint connection failure.'];
        }
    }

    public function checkLiveness(string $base64Image): array
    {
        if ($this->isTestMode()) {
            return [
                'success' => true,
                'entity' => [
                    'id' => 'test_selfie_'.uniqid(),
                    'reference_id' => 'test_ref_'.uniqid(),
                    'confidence' => 95,
                    'liveness_score' => 0.98,
                    'image' => $base64Image, // Return the image so it can be saved as profile picture
                ],
                'message' => 'Liveness check passed (test mode).',
            ];
        }

        try {
            return $this->provider->verifyFace(['image' => $base64Image]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Liveness validation gateway timed out.'];
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.dojah.webhook_secret');

        if (empty($secret)) {
            return false;
        }

        $signature = trim($signature);
        if ($signature !== '' && str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    public function storeResult(int $userId, string $type, array $result): KycVerification
    {
        $status = ($result['success'] ?? false) ? 'approved' : 'failed';

        return KycVerification::updateOrCreate(
            ['user_id' => $userId, 'verification_type' => $type],
            [
                'verification_id' => $result['entity']['id'] ?? $result['entity']['reference_id'] ?? null,
                'status' => $status,
                'response_json' => json_encode($result),
            ]
        );
    }

    public function extractSelfieImage(array $result): ?string
    {
        // Try to get image from various possible locations in the response
        return $result['entity']['image'] ??
               $result['entity']['selfie_image'] ??
               $result['entity']['photo'] ??
               null;
    }
}
