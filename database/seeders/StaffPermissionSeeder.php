<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StaffPermission;
use Spatie\Permission\Models\Role;

class StaffPermissionSeeder extends Seeder
{
    public function run()
    {
        $defaults = [
            'admin' => [
                'manage_transaction_charges' => true,
                'manage_services' => true,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => true,
            ],
            'manager' => [
                'manage_transaction_charges' => true,
                'manage_services' => true,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
            ],
            'accounts' => [
                'manage_transaction_charges' => true,
                'manage_services' => false,
                'manage_kyc_settings' => false,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
            ],
            'compliance' => [
                'manage_transaction_charges' => false,
                'manage_services' => false,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
            ],
            'support' => [
                'manage_transaction_charges' => false,
                'manage_services' => false,
                'manage_kyc_settings' => false,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
            ],
        ];

        foreach ($defaults as $role => $perms) {
            // Only create/update permissions if the role exists in roles table
            if (Role::where('name', $role)->exists()) {
                StaffPermission::updateOrCreate(['role' => $role], ['permissions' => $perms]);
            }
        }
    }
}
