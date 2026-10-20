<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_database_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseHas('roles', ['name' => 'admin', 'guard_name' => 'api']);
        $this->assertDatabaseHas('permissions', ['name' => 'view_reports', 'guard_name' => 'api']);
        $countsAfterFirstRun = $this->seededRecordCounts();
        $walletsAfterFirstRun = $this->seededWalletBalances();

        $this->seed(DatabaseSeeder::class);

        $countsAfterSecondRun = $this->seededRecordCounts();
        $walletsAfterSecondRun = $this->seededWalletBalances();

        $this->assertSame(
            $countsAfterFirstRun,
            $countsAfterSecondRun,
            json_encode(['first_run' => $countsAfterFirstRun, 'second_run' => $countsAfterSecondRun])
        );
        $this->assertSame($walletsAfterFirstRun, $walletsAfterSecondRun);
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
        $counts = [];

        foreach (Schema::getTableListing() as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        ksort($counts);

        return $counts;
    }

    /** @return array<int, array<string, mixed>> */
    private function seededWalletBalances(): array
    {
        return DB::table('wallets')
            ->orderBy('user_id')
            ->orderBy('currency')
            ->get(['user_id', 'currency', 'balance', 'ngn_cleared', 'usd_cleared', 'account_number'])
            ->map(fn (object $wallet): array => (array) $wallet)
            ->all();
    }
}
