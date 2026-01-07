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
        $user = auth()->user();

        $isAdmin = (isset($user->role) && strtolower($user->role) === 'admin') || $user->hasRole('admin');

        if (!$isAdmin && !StaffPermissionService::roleHasCapability($user, 'manage_transaction_charges')) {

            $assignedRoles = $user->getRoleNames();

            $staffRole = $assignedRoles->reject(fn($name) => $name === 'user')->first()
                ?? $user->role
                ?? 'Staff';

            return response()->json([
                'success' => false,
                'message' => "Restricted to admin. You are logged in as a '{$staffRole}' staff and cannot access the Transaction Charges configuration."
            ], 403);
        }

        return response()->json(TransactionCharge::all());
    }
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !StaffPermissionService::roleHasCapability($user, 'manage_transaction_charges')) {
            $assignedRoles = $user->getRoleNames();

            $staffRole = $assignedRoles->reject(fn($name) => $name === 'user')->first();

            $displayRole = $staffRole ?? 'Staff';

            return response()->json([
                'success' => false,
                'message' => "Restricted to admin. You are a '{$displayRole}' staff and cannot create transaction charges."
            ], 403);
        }

        $request->validate([
            'transaction_type' => 'required|string',
            'charge_type' => 'required|in:flat,percentage',
            'value' => 'required|numeric',
        ]);
        return TransactionCharge::create($request->all());
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user->hasRole('admin') && !StaffPermissionService::roleHasCapability($user, 'manage_transaction_charges')) {
            $assignedRoles = $user->getRoleNames();

            $staffRole = $assignedRoles->reject(fn($name) => $name === 'user')->first();

            $displayRole = $staffRole ?? 'Staff';

            return response()->json([
                'success' => false,
                'message' => "Restricted to admin. You are a '{$displayRole}' staff and cannot make transaction changes."
            ], 403);
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
                'details' => $details,
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
