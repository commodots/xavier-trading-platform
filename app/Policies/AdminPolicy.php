<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    /**
     * Only superadmin can toggle user verification status (prevent privilege escalation).
     */
    public function toggleUserStatus(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Only superadmin can disable/enable users.
     */
    public function toggleUserDisabled(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Only superadmin can access system settings.
     */
    public function viewSystemSettings(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Only superadmin can modify system settings (AML thresholds, trial days, etc).
     */
    public function editSystemSettings(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Analysts can view reports but not edit them.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasRole('superadmin', 'analyst');
    }
}
