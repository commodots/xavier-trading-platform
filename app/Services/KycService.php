<?php

namespace App\Services;

use App\Models\KycProfile;
use App\Models\KycSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * KYC Service - Handles KYC operations, data masking, and verification
 */
class KycService
{
    /**
     * Canonical verified statuses. Use this everywhere instead of
     * ad-hoc in_array checks to prevent status string scatter.
     */
    public const VERIFIED_STATUSES = ['verified', 'approved'];

    /**
     * Check if status matches canonical verified formats
     * * @param string|null $status
     * @return bool
     */
    public static function isVerified(?string $status): bool
    {
        return in_array($status, self::VERIFIED_STATUSES, true);
    }

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
        $maskLength = max(0, $length - $showDigits);

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
        $data['tin'] = self::maskPii($kyc->tin ?? null);
        
        return $data;
    }

    /**
     * Get full KYC data (unmasked) - only for backend/admin operations
     * This should only be called in controllers where authentication is verified
     *
     * @param KycProfile|null $kyc
     * @return array Full KYC data
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
     * Determine KYC tier based on verification level attributes
     * Aligns with Dojah Backend Integration Mapping
     *
     * @param KycProfile $kyc
     * @return int Tier level (0, 1, 2, or 3)
     */
    public static function determineTier(KycProfile $kyc): int
    {
        $user = $kyc->user ?? \App\Models\User::find($kyc->user_id);
        
        // Level 1 Base Entry: Check Email Verification state
        $level = ($user && $user->email_verified_at) ? 1 : 0;

        // Fetch verification approvals from the dynamic table
        $approvedVerifications = DB::table('kyc_verifications')
            ->where('user_id', $kyc->user_id)
            ->where('status', 'approved')
            ->pluck('verification_type')
            ->toArray();

        // Level 2: Core Document Checks (BVN or NIN presence matches database verification step)
        $hasIdentityDoc = (!empty($kyc->bvn) && strlen($kyc->bvn) >= 11) || (!empty($kyc->nin) && strlen($kyc->nin) >= 11);
        $isIdentityVerified = count(array_intersect(['bvn', 'nin'], $approvedVerifications)) > 0;

        if ($hasIdentityDoc || $isIdentityVerified) {
            $level = 2;
        }

        // Level 3: Advanced Liveness/Face verification check matching Dojah SDK Success payloads
        if ($level >= 2 && in_array('selfie', $approvedVerifications)) {
            $level = 3;
        }

        return $level;
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
                'level' => 'none',
                'daily_limit' => 0,
                'bvn' => 'Not Available',
                'nin' => 'Not Available',
            ];
        }

        $tier = self::determineTier($kyc);
        $setting = self::getKycSetting($tier);

        $levelLabels = [0 => 'none', 1 => 'basic', 2 => 'identity', 3 => 'biometric'];

        return [
            'id' => $kyc->id,
            'status' => $kyc->status,
            'verified' => self::isVerified($kyc->status),
            'tier' => $tier,
            'level' => $levelLabels[$tier] ?? ($kyc->level ?? 'none'),
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