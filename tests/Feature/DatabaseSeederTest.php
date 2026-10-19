<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_database_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $countsAfterFirstRun = $this->seededRecordCounts();

        $this->seed(DatabaseSeeder::class);

        $countsAfterSecondRun = $this->seededRecordCounts();

        $this->assertSame(
            $countsAfterFirstRun,
            $countsAfterSecondRun,
            json_encode(['first_run' => $countsAfterFirstRun, 'second_run' => $countsAfterSecondRun])
        );
    }

    public function test_full_database_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);

        app(DatabaseSeeder::class)->run();
    }

    /** @return array<string, int> */
    private function seededRecordCounts(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'transactions' => DB::table('transactions')->count(),
            'new_transactions' => DB::table('new_transactions_table')->count(),
            'orders' => DB::table('orders')->count(),
            'portfolios' => DB::table('portfolios')->count(),
            'linked_accounts' => DB::table('linked_accounts')->count(),
            'billing_records' => DB::table('billing_records')->count(),
            'activity_logs' => DB::table('activity_logs')->count(),
            'user_devices' => DB::table('user_devices')->count(),
            'departments' => DB::table('departments')->count(),
            'expense_categories' => DB::table('expense_categories')->count(),
            'vendors' => DB::table('vendors')->count(),
            'expenses' => DB::table('expenses')->count(),
            'platform_earnings' => DB::table('platform_earnings')->count(),
        ];
    }
}