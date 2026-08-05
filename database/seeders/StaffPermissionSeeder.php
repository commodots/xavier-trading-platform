<?php

namespace Database\Seeders;

use App\Models\StaffPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StaffPermissionSeeder extends Seeder
{
    public function run()
    {
        $defaults = [
            'super-admin' => [
                'manage_transaction_charges' => true,
                'manage_services' => true,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => true,
                'view_reports' => true,
                'view_executive_reports' => true,
                'view_financial_reports' => true,
                'view_investment_reports' => true,
                'view_user_reports' => true,
                'view_compliance_reports' => true,
                'export_reports' => true,
            ],
            'admin' => [
                'manage_transaction_charges' => true,
                'manage_services' => true,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => true,
                'view_reports' => true,
                'view_executive_reports' => true,
                'view_financial_reports' => true,
                'view_investment_reports' => true,
                'view_user_reports' => true,
                'view_compliance_reports' => true,
                'export_reports' => true,
            ],
            'manager' => [
                'manage_transaction_charges' => true,
                'manage_services' => true,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
                'view_reports' => true,
                'view_executive_reports' => true,
                'view_financial_reports' => true,
                'view_investment_reports' => true,
                'view_user_reports' => true,
                'view_compliance_reports' => true,
                'export_reports' => true,
            ],
            'accounts' => [
                'manage_transaction_charges' => true,
                'manage_services' => false,
                'manage_kyc_settings' => false,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
                'view_reports' => true,
                'view_executive_reports' => false,
                'view_financial_reports' => true,
                'view_investment_reports' => true,
                'view_user_reports' => true,
                'view_compliance_reports' => false,
                'export_reports' => true,
            ],
            'compliance' => [
                'manage_transaction_charges' => false,
                'manage_services' => false,
                'manage_kyc_settings' => true,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
                'view_reports' => true,
                'view_executive_reports' => true,
                'view_financial_reports' => true,
                'view_investment_reports' => false,
                'view_user_reports' => true,
                'view_compliance_reports' => true,
                'export_reports' => true,
            ],
            'support' => [
                'manage_transaction_charges' => false,
                'manage_services' => false,
                'manage_kyc_settings' => false,
                'manage_platform_earnings' => true,
                'manage_system_settings' => false,
                'view_reports' => true,
                'view_executive_reports' => false,
                'view_financial_reports' => false,
                'view_investment_reports' => false,
                'view_user_reports' => true,
                'view_compliance_reports' => false,
                'export_reports' => false,
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
