<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\KycProfile;
use App\Services\KycService;

class SyncVerifiedUserTiers extends Command
{
    protected $signature = 'kyc:sync-tiers';
    protected $description = 'Syncs and updates tiers for users who have already verified their emails';

   public function handle()
{
    $this->info('Starting KYC tier sync engine...');
    $updatedCount = 0;

    \App\Models\User::whereNotNull('email_verified_at')->chunk(100, function ($users) use (&$updatedCount) {
        foreach ($users as $user) {
            $kyc = \App\Models\KycProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['status' => 'approved', 'tier' => 0]
            );

            // 🚀 FIX: Use getRawOriginal to see if the database has plain-text fallback data
            $rawBvn = $kyc->getRawOriginal('bvn');
            $rawNin = $kyc->getRawOriginal('nin');

            // If the row data is plain text, we manually pass it. If it's empty, we check the relation.
            $hasIdentityDoc = !empty($rawBvn) || !empty($rawNin);

            // Fetch verification approvals from the database table
            $isIdentityVerified = \Illuminate\Support\Facades\DB::table('kyc_verifications')
                ->where('user_id', $user->id)
                ->where('status', 'verified')
                ->whereIn('verification_type', ['bvn', 'nin'])
                ->exists();

            // Determine the correct tier safely
            $calculatedTier = 1; // Default fallback for email verified
            if ($hasIdentityDoc || $isIdentityVerified) {
                $calculatedTier = 2;
            }

            // Check for selfie approval
            $hasSelfie = \Illuminate\Support\Facades\DB::table('kyc_verifications')
                ->where('user_id', $user->id)
                ->where('status', 'verified')
                ->where('verification_type', 'selfie')
                ->exists();

            if ($calculatedTier === 2 && $hasSelfie) {
                $calculatedTier = 3;
            }

            // Apply the update to your local users
            if ($kyc->tier < $calculatedTier) {
                $level = match($calculatedTier) {
                    1 => 'email_verified',
                    2 => 'identity_verified',
                    3 => 'fully_verified',
                    default => 'unverified',
                };

                $kyc->update([
                    'tier' => $calculatedTier,
                    'status' => 'verified',
                    'level' => $level
                ]);
                $updatedCount++;
            }
        }
    });

    $this->info("KYC tiers synced successfully!");
    $this->line("Successfully updated tier for {$updatedCount} records.");
}
}