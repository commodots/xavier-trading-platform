<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $categories = \App\Models\ExpenseCategory::all();
        $vendors = \App\Models\Vendor::all();
        $departments = \App\Models\Department::all();
        $statuses = ['draft', 'approved', 'paid', 'cancelled'];
        $paymentMethods = ['cash', 'bank_transfer', 'card', 'wallet', 'other'];

        $expenses = [
            // Technology department - AWS/Cloud services
            ['amount' => 350000, 'category' => 'Cloud Services', 'vendor' => 'AWS', 'department' => 'Technology', 'status' => 'paid', 'days_ago' => 5],
            ['amount' => 180000, 'category' => 'Software', 'vendor' => 'Microsoft Azure', 'department' => 'Technology', 'status' => 'paid', 'days_ago' => 12],
            ['amount' => 150000, 'category' => 'Hosting', 'vendor' => 'Google Cloud', 'department' => 'Technology', 'status' => 'approved', 'days_ago' => 3],
            ['amount' => 95000, 'category' => 'Internet', 'vendor' => 'MTN', 'department' => 'Technology', 'status' => 'paid', 'days_ago' => 20],
            
            // Marketing department
            ['amount' => 500000, 'category' => 'Marketing', 'vendor' => 'Meta', 'department' => 'Marketing', 'status' => 'paid', 'days_ago' => 8],
            ['amount' => 300000, 'category' => 'Advertising', 'vendor' => 'Google', 'department' => 'Marketing', 'status' => 'approved', 'days_ago' => 2],
            ['amount' => 150000, 'category' => 'Marketing', 'vendor' => 'Meta', 'department' => 'Marketing', 'status' => 'paid', 'days_ago' => 25],
            
            // Finance department
            ['amount' => 1200000, 'category' => 'Office Rent', 'vendor' => 'Office Landlord', 'department' => 'Finance', 'status' => 'paid', 'days_ago' => 1],
            ['amount' => 50000, 'category' => 'Bank Charges', 'vendor' => 'Paystack', 'department' => 'Finance', 'status' => 'paid', 'days_ago' => 7],
            ['amount' => 75000, 'category' => 'Payment Gateway Charges', 'vendor' => 'Flutterwave', 'department' => 'Finance', 'status' => 'paid', 'days_ago' => 14],
            ['amount' => 300000, 'category' => 'Professional Fees', 'vendor' => 'Audit Firm', 'department' => 'Finance', 'status' => 'approved', 'days_ago' => 10],
            
            // Operations department
            ['amount' => 200000, 'category' => 'Utilities', 'vendor' => 'Airtel', 'department' => 'Operations', 'status' => 'paid', 'days_ago' => 15],
            ['amount' => 85000, 'category' => 'Office Supplies', 'vendor' => null, 'department' => 'Operations', 'status' => 'draft', 'days_ago' => 1],
            ['amount' => 120000, 'category' => 'Maintenance', 'vendor' => null, 'department' => 'Operations', 'status' => 'paid', 'days_ago' => 30],
            
            // Human Resources
            ['amount' => 250000, 'category' => 'Training', 'vendor' => null, 'department' => 'Human Resources', 'status' => 'approved', 'days_ago' => 5],
            ['amount' => 180000, 'category' => 'Insurance', 'vendor' => 'Insurance Company', 'department' => 'Human Resources', 'status' => 'paid', 'days_ago' => 18],
            
            // More expenses across departments
            ['amount' => 450000, 'category' => 'Professional Fees', 'vendor' => 'Law Firm', 'department' => 'Finance', 'status' => 'paid', 'days_ago' => 22],
            ['amount' => 95000, 'category' => 'Software', 'vendor' => 'DigitalOcean', 'department' => 'Technology', 'status' => 'cancelled', 'days_ago' => 45],
            ['amount' => 175000, 'category' => 'Travel', 'vendor' => null, 'department' => 'Marketing', 'status' => 'draft', 'days_ago' => 2],
            ['amount' => 65000, 'category' => 'Repairs', 'vendor' => null, 'department' => 'Operations', 'status' => 'paid', 'days_ago' => 28],
            ['amount' => 220000, 'category' => 'Taxes', 'vendor' => null, 'department' => 'Finance', 'status' => 'approved', 'days_ago' => 12],
            ['amount' => 85000, 'category' => 'Licensing', 'vendor' => 'Microsoft Azure', 'department' => 'Technology', 'status' => 'paid', 'days_ago' => 35],
            ['amount' => 125000, 'category' => 'Advertising', 'vendor' => 'Google', 'department' => 'Marketing', 'status' => 'cancelled', 'days_ago' => 50],
            ['amount' => 380000, 'category' => 'Cloud Services', 'vendor' => 'AWS', 'department' => 'Technology', 'status' => 'paid', 'days_ago' => 40],
            ['amount' => 55000, 'category' => 'Miscellaneous', 'vendor' => null, 'department' => 'Operations', 'status' => 'draft', 'days_ago' => 1],
            ['amount' => 275000, 'category' => 'Training', 'vendor' => null, 'department' => 'Human Resources', 'status' => 'approved', 'days_ago' => 8],
            ['amount' => 140000, 'category' => 'Internet', 'vendor' => 'Airtel', 'department' => 'Technology', 'status' => 'paid', 'days_ago' => 60],
            ['amount' => 195000, 'category' => 'Maintenance', 'vendor' => null, 'department' => 'Operations', 'status' => 'paid', 'days_ago' => 55],
        ];

        foreach ($expenses as $index => $expenseData) {
            $category = $categories->firstWhere('name', $expenseData['category']);
            $vendor = $expenseData['vendor'] ? $vendors->firstWhere('name', $expenseData['vendor']) : null;
            $department = $departments->firstWhere('name', $expenseData['department']);
            $user = $users->random();
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            $expense = Expense::create([
                'expense_category_id' => $category->id,
                'vendor_id' => $vendor?->id,
                'department_id' => $department->id,
                'requested_by' => $user->id,
                'amount' => $expenseData['amount'],
                'currency' => 'NGN',
                'expense_date' => Carbon::now()->subDays($expenseData['days_ago'])->startOfDay(),
                'payment_method' => $paymentMethod,
                'reference' => 'REF-' . strtoupper(uniqid()),
                'invoice_number' => 'INV-' . date('Y') . '-' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'description' => "Expense for {$expenseData['category']} - {$expenseData['department']}",
                'status' => $expenseData['status'],
            ]);

            // Assign the ID-derived human-readable expense number (EXP-YYYYMM-000001)
            Expense::assignExpenseNo($expense);
        }
    }
}