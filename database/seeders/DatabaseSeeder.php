<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            WalletSeeder::class,
            KycSeeder::class,
            TransactionSeeder::class, ServiceSeeder::class,
            ServiceConnectionSeeder::class,
            ServiceConfigSeeder::class,
            StaffPermissionSeeder::class,
            AdvisorySeeder::class,
            CryptoSeeder::class,
            NewTransactionSeeder::class,
            OrderSeeder::class,
            PortfolioSeeder::class,
            StockSeeder::class,
            UserSettingsSeeder::class,
            StockSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
