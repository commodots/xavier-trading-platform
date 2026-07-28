<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformSetting;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            'site_name' => 'Xavier Trading Platform',
            'site_email' => 'support@xavier.com',
            'support_phone' => '+2348000000000',
            'timezone' => 'Africa/Lagos',
            
            // Investment
            'minimum_investment' => 10000,
            'maximum_investment' => 10000000,
            'default_roi' => 15,
            'lock_period_days' => 30,
            
            // Wallet
            'minimum_deposit' => 1000,
            'minimum_withdrawal' => 5000,
            'withdrawal_charge' => 50,
            'deposit_charge' => 0,
            
            // Referral
            'referral_commission_percentage' => 5,
            'referral_level_1_percentage' => 3,
            'referral_level_2_percentage' => 2,
            'referral_bonus_amount' => 1000,
            
            // Payment
            'manual_payment_enabled' => true,
            'fincra_enabled' => true,
            'ngx_enabled' => true,
            
            // Maintenance
            'maintenance_mode' => false,
            'registration_enabled' => true,
            'investments_enabled' => true,
            'withdrawals_enabled' => true,
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::set($key, $value);
        }
    }
}