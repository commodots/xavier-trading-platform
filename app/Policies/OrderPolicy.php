<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Only the order owner can view their own order (prevent account takeover enumeration).
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    /**
     * Only the order owner can cancel their own order (prevent cancelling others' trades).
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->id === $order->user_id && $order->status === 'open';
    }

    /**
     * Only authenticated users can create orders (prevents guest trading).
     */
    public function create(User $user): bool
    {
        return $user->email_verified_at !== null && $user->kyc_verified === true;
    }
}
