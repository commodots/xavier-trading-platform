<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemSetting;

class SystemSettingsController extends Controller
{
    public function get()
    {
        $settings = SystemSetting::first();

        if (!$settings) {
            $settings = SystemSetting::create([
                'company_name' => 'Xavier',
                'support_email' => 'support@xavier.com',
                'base_currency' => 'NGN',
                'trading_fee' => 0.5,
                'withdrawal_fee' => 50,
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
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $settings = SystemSetting::first();

        if (!$settings) {
            $settings = new SystemSetting();
        }

        $settings->fill($request->all());
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }
}
