<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpenseMvpTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    public function test_expense_mvp_tables_and_relationships_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('expense_categories'));
        $this->assertTrue(Schema::hasTable('vendors'));
        $this->assertTrue(Schema::hasTable('expenses'));
        $this->assertTrue(Schema::hasColumn('expenses', 'currency'));

        $category = ExpenseCategory::create([
            'name' => 'Utilities',
            'code' => 'UTIL',
            'description' => 'Utility expenses',
            'is_active' => true,
        ]);

        $vendor = Vendor::create([
            'name' => 'AWS',
            'contact_person' => 'Account Team',
            'email' => 'aws@example.com',
            'phone' => '123456',
            'address' => 'Cloud Plaza',
            'is_active' => true,
        ]);

        $expense = Expense::create([
            'expense_no' => 'EXP-TEST-000001',
            'expense_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'department_id' => null,
            'requested_by' => null,
            'amount' => 1250.50,
            'currency' => 'NGN',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REF-001',
            'invoice_number' => 'INV-001',
            'description' => 'Cloud hosting fee',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('expense_categories', ['code' => 'UTIL']);
        $this->assertDatabaseHas('vendors', ['name' => 'AWS']);
        $this->assertDatabaseHas('expenses', ['expense_no' => 'EXP-TEST-000001', 'currency' => 'NGN']);

        $loadedExpense = $expense->fresh();
        $this->assertTrue($loadedExpense->category()->exists());
        $this->assertTrue($loadedExpense->vendor()->exists());
    }
}
