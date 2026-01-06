<?php

namespace App\Services;

use App\Models\StaffPermission;
use Illuminate\Support\Facades\Log;

class StaffPermissionService
{
    // capability keys used in the platform
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
            // User instance
            $names = $roleOrUser->getRoleNames();
            $roleName = $names[0] ?? null;
        }

        if (!$roleName) return false;

        $sp = StaffPermission::forRole($roleName);
        if (!$sp) return false;

        $perms = $sp->permissions ?? [];
        return !empty($perms[$capability]);
    }
}
