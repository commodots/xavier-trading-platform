<?php

namespace App\Services\Kyc\Providers;

use App\Services\Kyc\Contracts\KycProviderInterface;
use App\Services\QoreidService;

class QoreIdProvider implements KycProviderInterface
{
    /**
     * Verify BVN via QoreID.
     */
    public function verifyBvn(string $bvn): array
    {
        return QoreidService::verify('bvn', $bvn);
    }

    /**
     * Verify NIN via QoreID.
     */
    public function verifyNin(string $nin): array
    {
        return QoreidService::verify('nin', $nin);
    }

    /**
     * Verify face via QoreID complex verification (BVN + NIN + selfie).
     */
    public function verifyFace(array $data): array
    {
        return QoreidService::verify2FA(
            $data['bvn'] ?? '',
            $data['nin'] ?? '',
            $data['image_path'] ?? '',
            $data['user_data'] ?? []
        );
    }
}