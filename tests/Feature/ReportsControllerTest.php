<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_users_requires_auth()
    {
        $res = $this->postJson('/api/admin/reports/export', [
            'format' => 'csv',
            'report_type' => 'users',
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-03',
        ]);

        $res->assertStatus(401);
    }

    public function test_export_users_as_admin_returns_file()
    {
        // Ensure role exists for the HasRoles trait
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        // Give admin role
        $admin->assignRole('super-admin');

        $res = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
            'format' => 'csv',
            'report_type' => 'users',
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-03',
        ]);

        // We expect a streamed response, status 200
        $res->assertStatus(200);
    }

    public function test_export_financial_wallet_transactions_tab_as_admin_returns_file()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $res = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
            'format' => 'csv',
            'report_type' => 'financial',
            'tab' => 'wallet_transactions',
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-03',
        ]);

        $res->assertStatus(200);
        $res->assertHeader('content-disposition', 'attachment; filename="wallet_transactions.csv"');
        $content = $res->streamedContent();
        $this->assertStringContainsString('Date,Reference,User,Type,Amount,Currency,Status', $content);
    }

    public function test_export_users_includes_all_users_in_export()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        User::factory()->count(60)->create();

        $res = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
            'format' => 'csv',
            'report_type' => 'users',
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-03',
        ]);

        $res->assertStatus(200);
        $res->assertHeader('content-disposition', 'attachment; filename="users.csv"');

        $content = $res->streamedContent();
        $this->assertStringContainsString('Name,Email,Phone,Country,"KYC Status",Status,Joined,"Last Login"', $content);
        $this->assertGreaterThanOrEqual(61, substr_count($content, "\n"));
    }
}
