<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionCharge;
use App\Models\ActivityLog;
use App\Models\NewTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionChargeController extends Controller
{
    public function index()
    {
        return response()->json(TransactionCharge::all());
    }
    public function store(Request $request)
    {
        $request->validate([
            'transaction_type' => 'required|string',
            'charge_type' => 'required|in:flat,percentage',
            'value' => 'required|numeric',
        ]);
    }

    public function update(Request $request, $id)
    {
        $charge = TransactionCharge::findOrFail($id);

        $oldValue = $charge->value;
        $oldType = $charge->charge_type;

        $charge->update($request->except('id'));

        try {
            $details = "Updated {$charge->transaction_type} charge. " .
                "Changed from {$oldValue} ({$oldType}) to {$charge->value} ({$charge->charge_type}).";

            ActivityLog::create([
            'user_id'    => auth()->id(),
            'activity'   => 'Charge Update', 
            'details'=> $details,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        } catch (\Throwable $e) {
            \Log::error("Activity Log failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Charge updated successfully',
            'charge' => $charge
        ]);
    }
}
