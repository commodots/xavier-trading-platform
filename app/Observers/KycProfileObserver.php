<?php

namespace App\Observers;

use App\Models\KycProfile;
use App\Services\KycService;
use App\Notifications\KycStatusNotification;

class KycProfileObserver
{
    /**
     * Handle the KycProfile "created" event.
     */
    public function created(KycProfile $kycProfile): void
    {
        //
    }

    /**
     * Handle the KycProfile "updated" event.
     */
    public function updated(KycProfile $kycProfile): void
    {
        //
    }

    /**
     * Handle the KycProfile "deleted" event.
     */
    public function deleted(KycProfile $kycProfile): void
    {
        //
    }

    /**
     * Handle the KycProfile "restored" event.
     */
    public function restored(KycProfile $kycProfile): void
    {
        //
    }

    /**
     * Handle the KycProfile "force deleted" event.
     */
    public function forceDeleted(KycProfile $kycProfile): void
    {
        //
    }
    public function saved(KycProfile $kyc): void
    {
        if (! $kyc->user) {
            return;
        }

        if ($kyc->wasChanged('status') || $kyc->wasChanged('bvn') || $kyc->wasChanged('nin') || $kyc->wasRecentlyCreated) {
            $kyc->user->update([
                'kyc_status' => $kyc->status,
                'bvn' => $kyc->bvn,
            ]);
        }

        if ($kyc->wasChanged('status')) {
            $kyc->user->notify(new KycStatusNotification(
                $kyc->status,
                $kyc->tier,
                $kyc->rejection_reason
            ));
        }

        if ($kyc->isVerified()) {
            $targetTier = KycService::determineTier($kyc);

            // Only auto-upgrade based on available documents. Do not downgrade a manually assigned tier.
            if ($targetTier > 0 && $targetTier > (int) $kyc->tier) {
                $tierSetting = KycService::getKycSetting($targetTier);
                
                KycProfile::withoutEvents(function () use ($kyc, $targetTier, $tierSetting) {
                    $kyc->update([
                        'tier' => $targetTier,
                        'level' => match ($targetTier) {
                            2 => 'mid',
                            3 => 'full',
                            default => 'basic',
                        },
                        'daily_limit' => $tierSetting?->daily_limit ?? $kyc->daily_limit,
                    ]);
                });
            }
        }
    }
}
