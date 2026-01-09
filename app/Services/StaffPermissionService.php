<?php

namespace App\Services;

use App\Models\StaffPermission;
use Illuminate\Support\Facades\Log;

class StaffPermissionService
{
    public const CAPABILITIES = [
        'manage_transaction_charges',
        'manage_services',
        'manage_kyc_settings',
        'manage_platform_earnings',
        'manage_system_settings',
    ];

    public static function roleHasCapability($roleOrUser, string $capability): bool
    {
        if (!in_array($capability, self::CAPABILITIES)) return false;

        $roleName = null;

        if (is_string($roleOrUser)) {
            $roleName = $roleOrUser;
        } elseif ($roleOrUser && method_exists($roleOrUser, 'getRoleNames')) {
            // Check Spatie roles first, ignoring generic ones
            $names = $roleOrUser->getRoleNames();
            $roleName = $names->reject(fn($name) => in_array($name, ['user', 'admin']))->first();
            
            // Fallback to the 'role' column on the User model if Spatie is empty
            if (!$roleName && isset($roleOrUser->role)) {
                $roleName = $roleOrUser->role;
            }
        }

        // Final check: if it's still empty or just 'user', deny access
        if (!$roleName || $roleName === 'user') {
            return false;
        }

        $sp = StaffPermission::forRole($roleName);
        if (!$sp) return false;

        $perms = $sp->permissions ?? [];

        // Return true only if the capability exists and is truthy
        return isset($perms[$capability]) && ($perms[$capability] === true || $perms[$capability] === 'true' || $perms[$capability] === 1 || $perms[$capability] === "1");
    }
}