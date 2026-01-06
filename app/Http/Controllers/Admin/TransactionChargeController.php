<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionCharge;
use App\Models\ActivityLog;
use App\Models\NewTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\StaffPermissionService;

class TransactionChargeController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_transaction_charges')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(TransactionCharge::all());
    }
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_transaction_charges')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'transaction_type' => 'required|string',
            'charge_type' => 'required|in:flat,percentage',
            'value' => 'required|numeric',
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasRole('admin') && !StaffPermissionService::roleHasCapability(auth()->user(), 'manage_transaction_charges')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
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
