<?php

namespace Database\Seeders\Production;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            StaffPermissionSeeder::class,
            SystemAdminSeeder::class,
            DepartmentSeeder::class,
            ExpenseCategorySeeder::class,
        ]);
    }
}