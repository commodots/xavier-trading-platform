<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function test_production_database_seeder_bootstraps_admin_and_preserves_existing_password(): void
    {
        $this->app['env'] = 'production';
        config(['production.system_admin' => [
            'name' => 'System Administrator',
            'email' => 'system-admin@example.invalid',
            'password' => 'long-test-password-for-admin',
        ]]);

        app(DatabaseSeeder::class)->run();

        $admin = User::query()->where('email', 'system-admin@example.invalid')->firstOrFail();

        $this->assertDatabaseHas('roles', ['name' => 'admin', 'guard_name' => 'api']);
        $this->assertDatabaseHas('permissions', ['name' => 'view_reports', 'guard_name' => 'api']);
        $this->assertDatabaseHas('departments', ['code' => 'TECH']);
        $this->assertDatabaseHas('expense_categories', ['code' => 'RENT']);
        $this->assertDatabaseCount('users', 0);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertTrue(Hash::check('long-test-password-for-admin', $admin->password));
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('orders', 0);

        $admin->forceFill(['password' => Hash::make('rotated-test-password')])->save();
        app(DatabaseSeeder::class)->run();

        $this->assertTrue(Hash::check('rotated-test-password', $admin->fresh()->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_production_database_seeder_requires_admin_credentials_for_first_bootstrap(): void
    {
        $this->app['env'] = 'production';
        config(['production.system_admin' => ['name' => 'System Administrator', 'email' => null, 'password' => null]]);
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
