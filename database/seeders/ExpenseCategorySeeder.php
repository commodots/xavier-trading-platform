<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Office Rent', 'code' => 'RENT', 'description' => 'Office space rental expenses'],
            ['name' => 'Utilities', 'code' => 'UTIL', 'description' => 'Electricity, water, and other utilities'],
            ['name' => 'Internet', 'code' => 'INT', 'description' => 'Internet and connectivity services'],
            ['name' => 'Hosting', 'code' => 'HOST', 'description' => 'Web hosting services'],
            ['name' => 'Cloud Services', 'code' => 'CLOUD', 'description' => 'Cloud platform services (AWS, Azure, GCP)'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Marketing and advertising expenses'],
            ['name' => 'Advertising', 'code' => 'ADV', 'description' => 'Digital and print advertising'],
            ['name' => 'Travel', 'code' => 'TRVL', 'description' => 'Business travel expenses'],
            ['name' => 'Training', 'code' => 'TRNG', 'description' => 'Employee training and development'],
            ['name' => 'Office Supplies', 'code' => 'SUPP', 'description' => 'Office supplies and materials'],
            ['name' => 'Software', 'code' => 'SW', 'description' => 'Software subscriptions and licenses'],
            ['name' => 'Licensing', 'code' => 'LIC', 'description' => 'Business and software licenses'],
            ['name' => 'Bank Charges', 'code' => 'BANK', 'description' => 'Bank fees and charges'],
            ['name' => 'Payment Gateway Charges', 'code' => 'PAYGW', 'description' => 'Payment processing fees'],
            ['name' => 'Professional Fees', 'code' => 'PROF', 'description' => 'Legal, accounting, and consulting fees'],
            ['name' => 'Insurance', 'code' => 'INS', 'description' => 'Business insurance premiums'],
            ['name' => 'Repairs', 'code' => 'REP', 'description' => 'Equipment and facility repairs'],
            ['name' => 'Maintenance', 'code' => 'MAINT', 'description' => 'Regular maintenance expenses'],
            ['name' => 'Taxes', 'code' => 'TAX', 'description' => 'Business taxes and levies'],
            ['name' => 'Miscellaneous', 'code' => 'MISC', 'description' => 'Other miscellaneous expenses'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::create($category);
        }
    }
}