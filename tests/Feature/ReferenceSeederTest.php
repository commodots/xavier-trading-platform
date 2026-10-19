<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\FxPair;
use App\Models\Vendor;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\FxPairSeeder;
use Database\Seeders\VendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_seeders_can_be_run_twice_without_duplicates(): void
    {
        $seeders = [
            DepartmentSeeder::class,
            ExpenseCategorySeeder::class,
            VendorSeeder::class,
            FxPairSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->seed($seeder);
            $this->seed($seeder);
        }

        $this->assertSame(5, Department::query()->count());
        $this->assertSame(20, ExpenseCategory::query()->count());
        $this->assertSame(14, Vendor::query()->count());
        $this->assertSame(3, FxPair::query()->count());
    }
}