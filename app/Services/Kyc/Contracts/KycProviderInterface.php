<?php

namespace App\Services\Kyc\Contracts;

interface KycProviderInterface
{
    /**
     * Verify a Bank Verification Number (BVN).
     */
    public function verifyBvn(string $bvn): array;

    /**
     * Verify a National Identification Number (NIN).
     */
    public function verifyNin(string $nin): array;

    /**
     * Verify face / liveness check.
     */
    public function verifyFace(array $data): array;
}