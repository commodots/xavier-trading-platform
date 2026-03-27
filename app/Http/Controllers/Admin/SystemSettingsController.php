<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function get()
    {
        $settings = SystemSetting::first();

        if (! $settings) {
            $settings = SystemSetting::create([
                'company_name' => 'Xavier',
                'support_email' => 'support@xavier.com',
                'base_currency' => 'NGN',
                'trading_fee' => 0.5,
                'withdrawal_fee' => 50,
                'crypto_spread' => 0,
                'crypto_fee' => 0,
                'max_trade_amount' => 10000,
                'ngx_api_key' => '',
                'global_api_key' => '',
                'crypto_api_key' => '',
                'paystack_public' => '',
                'paystack_secret' => '',
                'paystack_callback' => '',
                'dark_mode' => false,
                'maintenance_mode' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'trial_days' => 'nullable|integer|min:0',
            'trading_fee' => 'nullable|numeric|min:0|max:100',
            'withdrawal_fee' => 'nullable|numeric|min:0',
            'base_currency' => 'nullable|string|max:3',
            'company_name' => 'nullable|string|max:255',
            'support_email' => 'nullable|email',
            'crypto_spread' => 'nullable|numeric|min:0|max:100',
            'crypto_fee' => 'nullable|numeric|min:0|max:100',
            'max_trade_amount' => 'nullable|numeric|min:0',
        ]);

        $settings = SystemSetting::firstOrCreate([]);
        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $settings,
        ]);
    }
}
