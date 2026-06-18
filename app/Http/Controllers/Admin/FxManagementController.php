<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FxSetting;
use App\Models\FxPair;
use App\Services\Fx\Providers\FincraProvider;
use Illuminate\Http\Request;

class FxManagementController extends Controller
{
    public function index()
    {
        $setting = FxSetting::first();

        $pairs = FxPair::where('active', true)->get();

        $fincraStatus = null;
        if ($setting && $setting->provider === 'fincra') {
            try {
                $fincraProvider = app(FincraProvider::class);
                $fincraStatus = $fincraProvider->health();
            } catch (\Exception $e) {
                $fincraStatus = [
                    'status' => 'disconnected',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $setting,
                'pairs' => $pairs,
                'fincra_status' => $fincraStatus,
            ],
        ]);
    }

    public function switchProvider(Request $request)
    {
        $request->validate([
            'provider' => 'required|string|in:manual,fincra',
        ]);

        $setting = FxSetting::first();

        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'FX settings not found.'], 404);
        }

        $setting->update([
            'provider' => $request->provider,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FX provider switched to ' . $request->provider,
            'data' => $setting->fresh(),
        ]);
    }

    public function updatePair(Request $request, $id)
    {
        $request->validate([
            'buy_rate' => 'required|numeric|gt:0',
            'sell_rate' => 'required|numeric|gt:0',
        ]);

        $pair = FxPair::findOrFail($id);

        $pair->update([
            'buy_rate' => $request->buy_rate,
            'sell_rate' => $request->sell_rate,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FX pair updated successfully',
            'data' => $pair->fresh(),
        ]);
    }

    public function health()
    {
        $fincraProvider = app(FincraProvider::class);
        $health = $fincraProvider->health();

        return response()->json([
            'success' => true,
            'data' => $health,
        ]);
    }

    public function toggleAutoConvert(Request $request)
    {
        $request->validate([
            'auto_convert_stocks' => 'required|boolean',
        ]);

        $setting = FxSetting::first();

        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'FX settings not found.'], 404);
        }

        $setting->update([
            'auto_convert_stocks' => $request->auto_convert_stocks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Auto convert for stocks ' . ($request->auto_convert_stocks ? 'enabled' : 'disabled'),
            'data' => $setting->fresh(),
        ]);
    }

    public function conversions(Request $request)
    {
        $limit = $request->get('limit', 50);
        
        $conversions = \App\Models\FxConversion::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversions,
        ]);
    }
}
