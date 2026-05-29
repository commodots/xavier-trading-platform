<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\KycSetting;
use Illuminate\Support\Str;

/**
 * KYC Service - Handles KYC operations, data masking, and verification
 */
class KycService
{
    /**
     * Mask PII (Personally Identifiable Information) for safe display
     * Shows only last 4 digits, masks the rest with asterisks
     *
     * @param string|null $value
     * @param int $showDigits Number of digits to show at the end
     * @return string Masked value or "Not Available"
     */
    public static function maskPii(?string $value, int $showDigits = 4): string
    {
        if (empty($value)) {
            return 'Not Available';
        }

        $length = strlen($value);
        if ($length <= $showDigits) {
            return str_repeat('*', $length - $showDigits) . substr($value, -$showDigits);
        }

        $maskLength = $length - $showDigits;
        return str_repeat('*', $maskLength) . substr($value, -$showDigits);
    }

    /**
     * Get KYC data with masked PII for safe frontend display
     * Used when displaying KYC info to prevent accidental exposure
     *
     * @param KycProfile|null $kyc
     * @return array Masked KYC data
     */
    public static function getMaskedKycData(?KycProfile $kyc): array
    {
        if (!$kyc) {
            return [];
        }

        $data = $kyc->toArray();
        
        // Mask sensitive fields
        $data['bvn'] = self::maskPii($kyc->bvn);
        $data['nin'] = self::maskPii($kyc->nin);
        $data['tin'] = self::maskPii($kyc->tin);
        
        return $data;
    }

    /**
     * Get full KYC data (unmasked) - only for backend/admin operations
     * This should only be called in controllers where authentication is verified
     *
     * @param KycProfile|null $kyc
     * @return array Full KYC data with decrypted values
     */
    public static function getFullKycData(?KycProfile $kyc): array
    {
        if (!$kyc) {
            return [];
        }

        return $kyc->toArray();
    }

    /**
     * Get KYC tier configuration with daily limits
     *
     * @param int $tier
     * @return KycSetting|null
     */
    public static function getKycSetting(int $tier): ?KycSetting
    {
        return KycSetting::where('tier', $tier)->first();
    }

    /**
     * Determine KYC tier based on verification level
     *
     * @param KycProfile $kyc
     * @return int Tier level (1, 2, or 3)
     */
    public static function determineTier(KycProfile $kyc): int
    {
        if ($kyc->status !== 'verified' && $kyc->status !== 'approved') {
            return 0;
        }

        // Tier 1: Basic (BVN + NIN)
        if (!empty($kyc->bvn) && !empty($kyc->nin)) {
            return 1;
        }

        // Tier 2: Mid-level (Tier 1 + International Passport)
        if (!empty($kyc->intl_passport)) {
            return 2;
        }

        // Tier 3: Full Access (All documents)
        if (!empty($kyc->drivers_license) && !empty($kyc->proof_of_address)) {
            return 3;
        }

        return 1;
    }

    /**
     * Format KYC response for API consumption
     * Includes masked data for frontend and tier information
     *
     * @param KycProfile|null $kyc
     * @return array Formatted response
     */
    public static function formatKycResponse(?KycProfile $kyc): array
    {
        if (!$kyc) {
            return [
                'verified' => false,
                'status' => 'not_started',
                'tier' => 0,
                'daily_limit' => 0,
                'bvn' => 'Not Available',
                'nin' => 'Not Available',
            ];
        }

        $tier = $kyc->tier ?? self::determineTier($kyc);
        $setting = self::getKycSetting($tier);

        return [
            'id' => $kyc->id,
            'status' => $kyc->status,
            'verified' => in_array($kyc->status, ['approved', 'verified']),
            'tier' => $tier,
            'level' => $kyc->level ?? 'none',
            'daily_limit' => $setting?->daily_limit ?? 0,
            'currency' => $kyc->currency ?? 'NGN',
            'bvn' => self::maskPii($kyc->bvn),
            'nin' => self::maskPii($kyc->nin),
            'rejection_reason' => $kyc->rejection_reason,
            'created_at' => $kyc->created_at,
            'updated_at' => $kyc->updated_at,
        ];
    }

    /**
     * Extract QoreID webhook data and map to KYC fields
     * QoreID returns verification data that needs to be mapped to our schema
     *
     * @param array $webhookData
     * @return array Mapped data for KycProfile update
     */
    public static function extractQoreidData(array $webhookData): array
    {
        $mapped = [];

        // Map common QoreID response fields
        if (isset($webhookData['identity']['document']['type'])) {
            $mapped['id_type'] = $webhookData['identity']['document']['type'];
        }

        if (isset($webhookData['identity']['document']['number'])) {
            $mapped['id_number'] = $webhookData['identity']['document']['number'];
        }

        // Extract BVN if present
        if (isset($webhookData['identity']['bvn'])) {
            $mapped['bvn'] = $webhookData['identity']['bvn'];
        }

        // Extract NIN if present
        if (isset($webhookData['identity']['nin'])) {
            $mapped['nin'] = $webhookData['identity']['nin'];
        }

        // Extract personal info
        if (isset($webhookData['identity']['first_name'])) {
            $mapped['first_name'] = $webhookData['identity']['first_name'];
        }

        if (isset($webhookData['identity']['last_name'])) {
            $mapped['last_name'] = $webhookData['identity']['last_name'];
        }

        // Store the raw webhook response in meta for audit trail
        $mapped['meta'] = $webhookData;

        return $mapped;
    }

    /**
     * Validate required documents for a tier
     *
     * @param KycProfile $kyc
     * @param int $targetTier
     * @return array Missing documents
     */
    public static function validateTierRequirements(KycProfile $kyc, int $targetTier): array
    {
        $setting = self::getKycSetting($targetTier);
        
        if (!$setting || !$setting->required_documents) {
            return [];
        }

        $required = is_string($setting->required_documents) 
            ? json_decode($setting->required_documents, true) 
            : $setting->required_documents;

        $missing = [];

        foreach ($required as $doc) {
            if (empty($kyc->{$doc})) {
                $missing[] = $doc;
            }
        }

        return $missing;
    }
}
