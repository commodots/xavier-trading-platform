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

        // Expense management permissions
        $expensePermissions = [
            'view_expenses',
            'create_expenses',
            'edit_expenses',
            'approve_expenses',
            'pay_expenses',
            'cancel_expenses',
            'manage_expense_categories',
            'manage_vendors',
        ];

        foreach ($expensePermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Assign reporting permissions to admin role
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
            $adminRole->givePermissionTo($expensePermissions);
        }

        // Assign to super-admin
        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'api')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
            $superAdmin->givePermissionTo($expensePermissions);
        }

        // Assign to accounts/finance: View, Create, Edit, Approve, Pay
        $accounts = Role::where('name', 'accounts')->where('guard_name', 'api')->first();
        if ($accounts) {
            $accounts->givePermissionTo([
                'view_expenses',
                'create_expenses',
                'edit_expenses',
                'approve_expenses',
                'pay_expenses',
            ]);
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
