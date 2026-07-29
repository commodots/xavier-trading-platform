<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FxRateController extends Controller
{
    public function store(Request $request)
    {

        $user = auth()->user();
        $isAdmin = $user && (in_array(strtolower($user->role ?? ''), ['admin', 'super-admin']) 
                    || $user->hasRole(['super-admin', 'admin']));

        if (!$isAdmin) {
            return response()->json(['success' => false, 'message' => 'Forbidden: Admins only'], 403);
        }

        $validated = $request->validate([
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
            'base_rate' => 'required|numeric|gt:0',
            'markup_percent' => 'nullable|numeric|min:0',
        ]);

        try {
            $baseRate = (float) $validated['base_rate'];
            $markup = (float) ($validated['markup_percent'] ?? 0);
            $effectiveRate = $baseRate + ($baseRate * ($markup / 100));

            $rate = FxRate::create([
                'from_currency' => strtoupper($validated['from_currency']),
                'to_currency' => strtoupper($validated['to_currency']),
                'base_rate' => $baseRate,
                'markup_percent' => $markup,
                'effective_rate' => $effectiveRate,
            ]);

            $rate->base_rate = (float) $rate->base_rate;
            $rate->effective_rate = (float) $rate->effective_rate;
            $rate->markup_percent = (float) $rate->markup_percent;

            return response()->json(['success' => true, 'data' => $rate]);
        } catch (\Exception $e) {
            Log::error('Failed to store FX rate', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Unable to save FX rate'], 500);
        }
    }
    public function destroy($id)
    {
        $user = auth()->user();
        // Check if admin
        if (!$user || !in_array(strtolower($user->role ?? ''), ['admin', 'super-admin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $rate = FxRate::findOrFail($id);
            $rate->delete();

            return response()->json(['success' => true, 'message' => 'Rate deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete rate'], 500);
        }
    }
}
