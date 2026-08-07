<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            ['name' => 'Paystack', 'contact_person' => 'Support Team', 'email' => 'support@paystack.com', 'phone' => '+234-1-234-5678'],
            ['name' => 'Fincra', 'contact_person' => 'Account Manager', 'email' => 'info@fincra.com', 'phone' => '+234-1-876-5432'],
            ['name' => 'Flutterwave', 'contact_person' => 'Business Team', 'email' => 'business@flutterwave.com', 'phone' => '+234-1-555-1234'],
            ['name' => 'AWS', 'contact_person' => 'Account Team', 'email' => 'aws@amazon.com', 'phone' => '1-206-266-4015'],
            ['name' => 'Google Cloud', 'contact_person' => 'Support', 'email' => 'support@google.com', 'phone' => '1-650-253-0000'],
            ['name' => 'Microsoft Azure', 'contact_person' => 'Sales Team', 'email' => 'azure@microsoft.com', 'phone' => '1-800-642-7676'],
            ['name' => 'DigitalOcean', 'contact_person' => 'Support', 'email' => 'support@digitalocean.com', 'phone' => '1-347-903-7900'],
            ['name' => 'Meta', 'contact_person' => 'Business Support', 'email' => 'business@meta.com', 'phone' => '1-650-543-4800'],
            ['name' => 'MTN', 'contact_person' => 'Corporate Sales', 'email' => 'corporate@mtn.com', 'phone' => '+234-803-000-0000'],
            ['name' => 'Airtel', 'contact_person' => 'Business Team', 'email' => 'business@airtel.com', 'phone' => '+234-802-000-0000'],
            ['name' => 'Office Landlord', 'contact_person' => 'Property Manager', 'email' => 'manager@officelandlord.com', 'phone' => '+234-1-111-2222'],
            ['name' => 'Law Firm', 'contact_person' => 'Legal Team', 'email' => 'legal@lawfirm.com', 'phone' => '+234-1-333-4444'],
            ['name' => 'Audit Firm', 'contact_person' => 'Audit Team', 'email' => 'audit@auditfirm.com', 'phone' => '+234-1-555-6666'],
            ['name' => 'Insurance Company', 'contact_person' => 'Agent', 'email' => 'agent@insurance.com', 'phone' => '+234-1-777-8888'],
        ];

        foreach ($vendors as $vendor) {
            Vendor::create($vendor);
        }
    }
}