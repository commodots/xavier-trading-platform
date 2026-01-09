<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DummyCscsController extends Controller
{
    public function settle(Request $request)
    {
        $request->validate([
            'trade_id' => 'required|string',
            'amount' => 'required|numeric',
            'cycle' => 'required|string',
        ]);

        return response()->json([
            'status' => 'queued',
            'settlement_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    public function settlementStatus($trade_id)
    {
        return response()->json([
            'trade_id' => $trade_id,
            'status' => 'settled',
            'settlement_date' => now()->toDateString(),
        ]);
    }
}