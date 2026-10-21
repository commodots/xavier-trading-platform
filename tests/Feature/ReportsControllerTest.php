<?php

namespace Tests\Feature;

use App\Models\FixedIncomeInvestment;
use App\Models\FixedIncomeProduct;
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
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
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
            'start_date' => '2000-01-01',
            'end_date' => '2099-12-31',
        ]);

        $res->assertStatus(200);
        $res->assertHeader('content-disposition', 'attachment; filename="users.csv"');

        $content = $res->streamedContent();
        $this->assertStringContainsString('Name,Email,Phone,Country,"KYC Status",Status,Joined,"Last Login"', $content);
        $this->assertGreaterThanOrEqual(61, substr_count($content, "\n"));
    }

    public function test_wallet_report_tabs_export_csv_without_calling_missing_methods()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        foreach (['withdrawals', 'wallet_ledger', 'adjustments'] as $tab) {
            $response = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
                'format' => 'csv',
                'report_type' => 'wallet-withdrawals',
                'tab' => $tab,
                'start_date' => '2000-01-01',
                'end_date' => '2099-12-31',
            ]);

            $response->assertOk();
            $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        }
    }

    public function test_fixed_income_roi_and_maturity_exports_use_investment_values()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $product = FixedIncomeProduct::create([
            'name' => 'Audit Bond',
            'code' => 'AUDIT-BOND',
            'currency' => 'NGN',
            'status' => 'active',
            'minimum_amount' => 1000,
        ]);
        FixedIncomeInvestment::create([
            'user_id' => $admin->id,
            'fixed_income_product_id' => $product->id,
            'reference' => 'FI-REPORT-001',
            'principal_amount' => 1000,
            'currency' => 'NGN',
            'expected_interest' => 120,
            'actual_interest' => 60,
            'expected_maturity_amount' => 1120,
            'actual_maturity_amount' => 1060,
            'status' => 'active',
            'investment_date' => now(),
            'maturity_date' => now()->addDays(30),
        ]);

        foreach (['roi', 'maturity', 'investment-plans'] as $reportType) {
            $response = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
                'format' => 'csv',
                'report_type' => $reportType,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
            ]);

            $response->assertOk();
            $content = $response->streamedContent();
            $this->assertStringContainsString('Audit Bond', $content);
            if ($reportType === 'investment-plans') {
                $this->assertStringContainsString('120', $content);
            } else {
                $this->assertStringContainsString('FI-REPORT-001', $content);
            }
        }
    }

    public function test_fixed_income_report_supports_csv_excel_and_pdf()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        foreach (['csv' => '.csv', 'excel' => '.xlsx', 'pdf' => '.pdf'] as $format => $extension) {
            $response = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
                'format' => $format,
                'report_type' => 'fixed-income-investments',
            ]);

            $response->assertOk();
            $this->assertStringContainsString($extension, $response->headers->get('content-disposition'));
        }
    }

    public function test_expense_report_export_passes_a_request_to_the_report_service()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin)->postJson('/api/admin/reports/export', [
            'format' => 'csv',
            'report_type' => 'expenses',
            'start_date' => '2000-01-01',
            'end_date' => '2099-12-31',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Expense No', $response->streamedContent());
    }

    public function test_core_report_pages_load_with_an_empty_report_database()
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        foreach (['roi', 'maturity', 'investment-plans', 'profit-loss', 'expenses'] as $report) {
            $this->actingAs($admin)
                ->getJson("/api/admin/reports/{$report}")
                ->assertOk();
        }
    }
}
