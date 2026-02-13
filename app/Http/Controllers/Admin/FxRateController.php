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
        $this->authorize('admin');

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

            return response()->json(['success' => true, 'data' => $rate]);
        } catch (\Exception $e) {
            Log::error('Failed to store FX rate', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Unable to save FX rate'], 500);
        }
    }
}
