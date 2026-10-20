<?php

namespace Database\Seeders\DummyData;

use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ComplianceSeeder::class,
            WalletSeeder::class,
            KycSeeder::class,
            TransactionSeeder::class,
            ServiceSeeder::class,
            ServiceConnectionSeeder::class,
            ServiceConfigSeeder::class,
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
            VendorSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}