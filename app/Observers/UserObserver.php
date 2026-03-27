<?php

namespace App\Observers;

use App\Models\User;
use App\Services\TatumService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $tronMasterWalletId = config('services.crypto.master_tron_wallet_id');

        if ($tronMasterWalletId) {
            try {
                // Call Tatum service to generate the TRON address
                app(TatumService::class)->generateTronAddress($user->id);
            } catch (\Exception $e) {
                Log::error("Failed to generate TRON address for User {$user->id}: ".$e->getMessage());
            }
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
