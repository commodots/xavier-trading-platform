<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DatabaseSeeder includes demo fixtures and cannot run in production.');
        }

        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            WalletSeeder::class,
            KycSeeder::class,
            ComplianceSeeder::class,
            TransactionSeeder::class,
            ServiceSeeder::class,
            ServiceConnectionSeeder::class,
            ServiceConfigSeeder::class,
            StaffPermissionSeeder::class,
            AdvisorySeeder::class,
            CryptoSeeder::class,
            NewTransactionSeeder::class,
            OrderSeeder::class,
            PortfolioSeeder::class,
            UserSettingsSeeder::class,
            FxRateSeeder::class,
            FxConfigSeeder::class,
            FxSettingSeeder::class,
            FxPairSeeder::class,
            DepartmentSeeder::class,
            ExpenseCategorySeeder::class,
            VendorSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}
