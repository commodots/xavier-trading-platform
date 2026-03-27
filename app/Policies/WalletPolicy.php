<?php

namespace App\Policies;

use App\Models\User;

class WalletPolicy
{
    /**
     * Only the user can withdraw from their own wallet (prevent cross-user theft).
     */
    public function withdraw(User $user, User $owner): bool
    {
        return $user->id === $owner->id && $user->two_factor_confirmed_at !== null;
    }

    /**
     * Only the user can deposit to their own wallet (prevent forced transfers).
     */
    public function deposit(User $user, User $owner): bool
    {
        return $user->id === $owner->id;
    }

    /**
     * Only staff with proper role can view other users' wallets (for support).
     */
    public function viewOther(User $user, User $owner): bool
    {
        return $user->hasRole('support') && $user->id !== $owner->id;
    }
}
