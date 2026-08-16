<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['super-admin', 'admin', 'user', 'accounts', 'compliance', 'manager', 'support'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'api'
            ]);

            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web'
            ]);
        }

        // Reporting permissions
        $permissions = [
            'view_reports',
            'view_executive_reports',
            'view_financial_reports',
            'export_reports',
            'view_audit_reports',
            'reports.expenses.view',
            'reports.revenue.view',
            'reports.profit_loss.view',
            'reports.financial_summary.view',
            'reports.executive_dashboard.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Assign reporting permissions to admin role
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        // Assign to super-admin
        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'api')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Assign to compliance
        $compliance = Role::where('name', 'compliance')->where('guard_name', 'api')->first();
        if ($compliance) {
            $compliance->givePermissionTo(['view_reports', 'view_executive_reports', 'view_financial_reports', 'export_reports']);
        }

        // Assign to manager
        $manager = Role::where('name', 'manager')->where('guard_name', 'api')->first();
        if ($manager) {
            $manager->givePermissionTo(['view_reports', 'view_executive_reports', 'view_financial_reports', 'export_reports']);
        }
    }
}
