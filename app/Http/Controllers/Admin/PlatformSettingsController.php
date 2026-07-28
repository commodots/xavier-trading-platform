<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsController extends Controller
{
    public function index()
    {
        $settings = PlatformSetting::all()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable'
        ]);

        foreach ($validated['settings'] as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }

    public function clearCache()
    {
        PlatformSetting::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Settings cache cleared'
        ]);
    }
}