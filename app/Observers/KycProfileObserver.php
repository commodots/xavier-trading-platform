<?php

namespace App\Observers;

use App\Models\KycProfile;

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
    public function saved(KycProfile $kyc)
{
    if ($kyc->user) {
        
        if ($kyc->wasChanged('status') || $kyc->wasChanged('bvn') || $kyc->wasRecentlyCreated) {
            $kyc->user->update([
                'kyc_status' => $kyc->status,
                'bvn'        => $kyc->bvn,
            ]);
        }
    }
}
}
